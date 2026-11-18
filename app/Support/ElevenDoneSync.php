<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Certificate;
use App\Notifications\BookingUpdated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Keeps Letting World bookings and their 11Done bookings in step.
 *
 *  push()  – sends a paid booking to 11Done (safe to repeat: 11Done de-duplicates by reference)
 *  apply() – takes 11Done's view of the booking (webhook or poll) and updates Letting World:
 *            status, engineer, appointment, and on completion the new certificate.
 */
class ElevenDoneSync
{
    /** 11Done order_status → Letting World booking status. */
    private const STATUS = [
        'pending' => 'requested',
        'accepted' => 'confirmed',
        'completed' => 'completed',
        'rejected' => 'cancelled',
    ];

    public function __construct(private ElevenDone $elevenDone, private Payments $payments) {}

    /** Returns true when 11Done accepted the booking. Failures are recorded for retry. */
    public function push(Booking $booking): bool
    {
        if (! in_array($booking->sync_status, ['pending', 'failed'], true) || ! $this->elevenDone->enabled()) {
            return false;
        }

        $booking->loadMissing('property.landlord', 'property.tenants');
        $property = $booking->property;
        $landlord = $property->landlord;
        // 11Done contacts this tenant (access / choosing the time), so pick one it can reach.
        $tenant = $property->tenants->first(fn ($t) => filled($t->email) || filled($t->phone)) ?? $property->tenants->first();

        try {
            $data = ElevenDone::attempt(fn () => $this->elevenDone->createBooking(array_filter([
                'external_reference' => $booking->reference,
                'service_id' => $booking->provider_service_id,
                'amount_paid' => (float) $booking->price,
                'payment_reference' => $booking->payment_intent_id,
                'scheduling' => $booking->scheduling === 'tenant' ? 'tenant' : 'customer',
                'booking_date' => $booking->preferred_date?->toDateString(),
                'time_slot' => $booking->time_slot,
                'access' => $booking->access,
                'access_notes' => $booking->access_notes,
                'landlord' => [
                    'name' => trim("{$landlord->first_name} {$landlord->last_name}"),
                    'email' => $landlord->email,
                    'phone' => $landlord->phone,
                ],
                'property' => [
                    'address' => $property->fullAddress(),
                    'postcode' => $property->postcode,
                    'bedrooms' => $property->bedrooms,
                    'property_type' => $property->property_type,
                ],
                'answers' => $booking->service_answers ?: null,
                'tenant' => $tenant ? array_filter([
                    'name' => trim("{$tenant->first_name} {$tenant->last_name}"),
                    'email' => $tenant->email,
                    'phone' => $tenant->phone,
                ]) : null,
            ], fn ($v) => $v !== null)));
        } catch (Throwable $e) {
            $booking->forceFill([
                'sync_status' => 'failed',
                'sync_error' => mb_substr($e->getMessage(), 0, 1000),
                'sync_attempts' => $booking->sync_attempts + 1,
            ])->save();
            Log::warning('Sending booking to 11Done failed', ['booking' => $booking->reference, 'error' => $e->getMessage()]);

            return false;
        }

        $booking->forceFill(['sync_status' => 'sent', 'sync_error' => null, 'sync_attempts' => $booking->sync_attempts + 1])->save();
        $this->apply($booking, $data);

        return true;
    }

    /** Applies 11Done's current state to the Letting World booking. */
    public function apply(Booking $booking, array $data): void
    {
        $booking->loadMissing('property.landlord');
        $previous = $booking->status;
        $status = self::STATUS[$data['order_status'] ?? ''] ?? $booking->status;
        // Letting World cancellations stay cancelled even if 11Done still says pending.
        if ($previous === 'cancelled') {
            $status = 'cancelled';
        }

        $slot = isset($data['start_time'], $data['end_time']) && $data['start_time']
            ? Carbon::parse($data['start_time'])->format('h:i A').' - '.Carbon::parse($data['end_time'])->format('h:i A')
            : null;

        $booking->forceFill([
            'provider_reference' => $data['order_number'] ?? $booking->provider_reference,
            'provider_status' => $data['order_status'] ?? $booking->provider_status,
            'sync_status' => 'sent',
            'synced_at' => now(),
            // While pending 11Done has only picked the company; show the engineer once accepted.
            'engineer_name' => in_array($status, ['confirmed', 'completed'], true)
                ? ($data['engineer']['name'] ?? $booking->engineer_name)
                : $booking->engineer_name,
            'scheduled_date' => $data['booking_date'] ?? $booking->scheduled_date,
            'scheduled_slot' => $slot ?? $booking->scheduled_slot,
            'status' => $status,
            'completed_at' => isset($data['completed_at']) ? Carbon::parse($data['completed_at']) : $booking->completed_at,
        ])->save();

        if ($status === 'completed' && ! $booking->result_certificate_id && ! empty($data['certificate']['url'])) {
            $this->attachCertificate($booking, $data);
        }

        if ($status === 'cancelled' && $previous !== 'cancelled') {
            $this->refundIfPaid($booking);
        }

        if ($status !== $previous && in_array($status, ['confirmed', 'completed', 'cancelled'], true)) {
            BookingUpdated::sendFor($booking->fresh(), $status);
        }
    }

    /** Downloads the engineer's certificate and adds it to the property (new expiry date). */
    private function attachCertificate(Booking $booking, array $data): void
    {
        try {
            $response = Http::timeout(30)->get($data['certificate']['url']);
            if (! $response->successful() || $response->body() === '') {
                throw new \RuntimeException("certificate download returned {$response->status()}");
            }

            $extension = strtolower(pathinfo($data['certificate']['file_name'] ?? '', PATHINFO_EXTENSION)) ?: 'pdf';
            // 11Done stores random names; give the landlord a readable one.
            $original = Certificate::typeLabel($booking->service_type).' - '.($booking->provider_reference ?: $booking->reference).".$extension";
            $path = "certificates/{$booking->property_id}/".Str::uuid().".$extension";
            Storage::disk('local')->put($path, $response->body());

            $issued = isset($data['completed_at']) ? Carbon::parse($data['completed_at'])->startOfDay() : today();
            $expiry = $data['certificate']['expiry_date'] ?? null;
            $months = config("lettingworld.certificate_types.{$booking->service_type}.months");
            if (! $expiry && ! ($data['certificate']['has_no_expiry'] ?? false) && $months) {
                $expiry = $issued->copy()->addMonthsNoOverflow($months)->toDateString();
            }

            DB::transaction(function () use ($booking, $path, $original, $response, $issued, $expiry, $data) {
                $certificate = $booking->property->certificates()->create([
                    'type' => $booking->service_type,
                    'issue_date' => $issued->toDateString(),
                    'expiry_date' => $expiry,
                    'provider_name' => $data['engineer']['name'] ?? '11Done',
                    'notes' => "Issued by 11Done (order {$booking->provider_reference}, booking {$booking->reference}).",
                    'file_path' => $path,
                    'file_name' => $original,
                    'file_mime' => $response->header('Content-Type') ?: 'application/pdf',
                    'file_size' => strlen($response->body()),
                ]);
                $booking->forceFill(['result_certificate_id' => $certificate->id])->save();
            });
        } catch (Throwable $e) {
            // Leave result_certificate_id empty so the next sync tries again.
            Log::warning('Attaching 11Done certificate failed', ['booking' => $booking->reference, 'error' => $e->getMessage()]);
        }
    }

    private function refundIfPaid(Booking $booking): void
    {
        if ($booking->payment_status !== 'paid' || ! $booking->payment_intent_id) {
            return;
        }
        try {
            $this->payments->refund($booking->payment_intent_id);
            $booking->forceFill(['payment_status' => 'refunded', 'cancelled_at' => $booking->cancelled_at ?? now()])->save();
        } catch (Throwable $e) {
            report($e); // refund can be retried by an admin; never block the status update
        }
    }
}
