<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Certificate;
use App\Models\Property;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Notifications\TenantAppointmentNotice;
use App\Support\BookingCancellation;
use App\Support\ElevenDone;
use App\Support\ElevenDoneSync;
use App\Support\Payments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Landlord-only: one-click certificate renewals, fulfilled by 11Done.
 *
 * 1. Quote: everything already known (property, certificate, tenants, saved card) plus the
 *    price, dates and time slots from 11Done for this property.
 * 2. Confirm: charge the saved card, save the booking, mark the certificate "Renewal booked",
 *    then send it to 11Done, where it becomes a normal, already-paid 11Done booking.
 * 3. 11Done reports back (webhook + polling): engineer assigned → completed → new certificate.
 */
class BookingController extends Controller
{
    public function __construct(private Payments $payments, private ElevenDone $elevenDone, private ElevenDoneSync $sync) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $bookings = $request->user()->bookings()
            ->with('property')
            ->when($request->query('propertyId'), fn ($q, $id) => $q->where('property_id', $id))
            ->latest()
            ->get();

        return BookingResource::collection($bookings);
    }

    /** Certificate renewals that can be booked (prices depend on the property — see quote). */
    public function services(): JsonResponse
    {
        // 11Done services (priced per property) plus the ones the Letting World team arranges.
        $viaElevenDone = $this->elevenDone->enabled() ? array_keys(array_filter(config('lettingworld.elevendone_services'))) : [];
        $types = array_values(array_unique([...$viaElevenDone, ...array_keys(config('lettingworld.service_prices'))]));

        return response()->json(collect($types)->map(fn ($type) => [
            'type' => $type,
            'label' => Certificate::typeLabel($type),
            'price' => in_array($type, $viaElevenDone, true) ? null : (float) config("lettingworld.service_prices.$type"),
        ])->values());
    }

    /** Pre-filled confirmation screen data for "Renew Now". */
    public function quote(Request $request): JsonResponse
    {
        $landlord = $request->user();
        $data = $request->validate([
            'propertyId' => ['required'],
            'serviceType' => ['required', Rule::in(array_keys(config('lettingworld.certificate_types')))],
            'certificateId' => ['nullable'],
        ]);

        $property = $landlord->properties()->active()->with('tenants')->findOrFail($data['propertyId']);
        $certificate = $this->certificateFor($property, $data['serviceType'], $data['certificateId'] ?? null);
        $card = $this->payments->defaultCard($landlord);
        $pricing = $this->pricing($property, $data['serviceType']);
        $tenantContact = $property->tenants->first(fn (User $t) => filled($t->email) || filled($t->phone));

        return response()->json([
            'property' => [
                'id' => (string) $property->id,
                'address' => $property->fullAddress(),
                'shortAddress' => $property->shortAddress(),
                'postcode' => $property->postcode,
            ],
            'service' => ['type' => $data['serviceType'], 'label' => Certificate::typeLabel($data['serviceType'])],
            'certificate' => $certificate ? [
                'id' => (string) $certificate->id,
                'expiryDate' => $certificate->expiry_date?->toDateString(),
                'status' => $certificate->status(),
            ] : null,
            'price' => $pricing['price'],
            'bookable' => $pricing['bookable'],
            'unavailableReason' => $pricing['reason'],
            'provider' => $pricing['provider'],
            'minDate' => $pricing['min_date'],
            'timeSlots' => $pricing['time_slots'],
            // 11Done can send the tenant a link to pick the appointment time themselves.
            'tenantCanSchedule' => $pricing['provider'] === '11done' && $tenantContact !== null,
            'tenants' => $property->tenants->map(fn (User $t) => [
                'name' => trim("{$t->first_name} {$t->last_name}"),
                'phone' => $t->phone,
            ])->values(),
            'landlord' => ['name' => trim("{$landlord->first_name} {$landlord->last_name}"), 'phone' => $landlord->phone, 'email' => $landlord->email],
            'paymentsConfigured' => $this->payments->configured(),
            'card' => $card,
            'openBooking' => $this->openBookingExists($landlord, $property->id, $data['serviceType']),
        ]);
    }

    /** Confirm Booking. */
    public function store(Request $request): JsonResponse
    {
        $landlord = $request->user();

        $base = $request->validate([
            'propertyId' => ['required', Rule::exists('properties', 'id')->where('landlord_id', $landlord->id)->whereNull('archived_at')],
            'serviceType' => ['required', Rule::in(array_keys(config('lettingworld.certificate_types')))],
        ], ['propertyId.exists' => 'Select one of your properties.'], ['propertyId' => 'property', 'serviceType' => 'service']);

        $property = Property::with('tenants')->findOrFail($base['propertyId']);
        $pricing = $this->pricing($property, $base['serviceType']);
        if (! $pricing['bookable']) {
            throw ValidationException::withMessages(['serviceType' => $pricing['reason'] ?? 'This service can’t be booked online.']);
        }

        $data = $request->validate([
            'certificateId' => ['nullable', Rule::exists('certificates', 'id')->where('property_id', $property->id)],
            'scheduling' => ['nullable', Rule::in(['landlord', 'tenant'])],
            'preferredDate' => ['required_unless:scheduling,tenant', 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.$pricing['min_date']],
            'timeSlot' => ['required_unless:scheduling,tenant', 'nullable', Rule::in($pricing['time_slots'])],
            'access' => ['required', Rule::in(['tenant', 'landlord', 'other'])],
            'accessNotes' => ['nullable', 'required_if:access,other', 'string', 'max:1000'],
            'notifyTenant' => ['required', 'boolean'],
        ], [
            'preferredDate.after_or_equal' => 'The earliest available date is '.date('j M Y', strtotime($pricing['min_date'])).'.',
            'preferredDate.required_unless' => 'Choose a date.',
            'timeSlot.required_unless' => 'Choose a time slot.',
            'accessNotes.required_if' => 'Tell the engineer how to get access.',
        ], ['preferredDate' => 'preferred date', 'timeSlot' => 'time slot', 'accessNotes' => 'access details']);

        $tenantScheduling = ($data['scheduling'] ?? 'landlord') === 'tenant';
        if ($tenantScheduling && ($pricing['provider'] !== '11done' || ! $property->tenants->first(fn ($t) => filled($t->email) || filled($t->phone)))) {
            throw ValidationException::withMessages(['scheduling' => 'The tenant can’t be asked to choose a time for this booking.']);
        }

        if ($this->openBookingExists($landlord, $property->id, $base['serviceType'])) {
            throw ValidationException::withMessages(['serviceType' => 'A renewal is already booked for this certificate at this property.']);
        }

        $price = $pricing['price'];
        $charge = $this->payments->configured();
        abort_if($charge && ! $this->payments->defaultCard($landlord), 402, 'Add a payment method before booking.');

        $booking = $landlord->bookings()->create([
            'property_id' => $property->id,
            'certificate_id' => $data['certificateId'] ?? null,
            'service_type' => $base['serviceType'],
            'preferred_date' => $tenantScheduling ? null : $data['preferredDate'],
            'scheduling' => $tenantScheduling ? 'tenant' : 'landlord',
            'time_slot' => $tenantScheduling ? null : $data['timeSlot'],
            'access' => $data['access'],
            'access_notes' => $data['accessNotes'] ?? null,
            'notify_tenant' => $data['notifyTenant'],
            'status' => 'requested',
            'price' => $price,
            'payment_status' => $charge ? 'pending' : 'not_charged',
            'provider' => $pricing['provider'],
            'provider_service_id' => $pricing['service_id'],
            'sync_status' => $pricing['provider'] === '11done' ? 'pending' : 'not_required',
        ]);
        $booking->load('property.tenants');

        if ($charge) {
            try {
                $paid = $this->payments->charge(
                    $landlord,
                    (int) round($price * 100),
                    Certificate::typeLabel($booking->service_type).' — '.$booking->property->shortAddress(),
                    ['booking_reference' => $booking->reference, 'booking_id' => $booking->id],
                );
            } catch (Throwable $e) {
                $booking->delete(); // no charge, no booking
                throw $e;
            }
            $booking->update([
                'payment_status' => 'paid',
                'payment_intent_id' => $paid['id'],
                'card_brand' => $paid['brand'],
                'card_last4' => $paid['last4'],
            ]);
        }

        // Hand over to 11Done now; if it's unreachable the scheduler retries every few minutes.
        $this->sync->push($booking);
        $booking->refresh()->load('property.tenants');

        $landlord->notify(new BookingConfirmed($booking));
        // With tenant scheduling, 11Done itself sends the tenant a link to choose the time.
        if (! $tenantScheduling && $booking->notify_tenant && $booking->property->tenants->isNotEmpty()) {
            Notification::send($booking->property->tenants, new TenantAppointmentNotice($booking));
        }

        return (new BookingResource($booking))->response()->setStatusCode(201);
    }

    /** Cancels (in 11Done too) and refunds a paid booking. */
    public function cancel(Request $request, string $id, BookingCancellation $cancellation): BookingResource
    {
        $booking = $request->user()->bookings()->with('property')->findOrFail($id);

        try {
            $cancellation->cancel($booking);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return new BookingResource($booking);
    }

    /**
     * Price, dates and slots for a renewal at this property.
     *
     * @return array{provider: string, service_id: ?int, price: ?float, bookable: bool, reason: ?string, min_date: string, time_slots: list<string>}
     */
    private function pricing(Property $property, string $type): array
    {
        $serviceId = $this->elevenDone->enabled() ? $this->elevenDone->serviceFor($type) : null;

        // Services 11Done doesn't offer are arranged by the Letting World team (admin panel).
        if (! $serviceId) {
            return $this->teamArranged($type);
        }

        try {
            $quote = ElevenDone::attempt(fn () => $this->elevenDone->quote($serviceId, $property->postcode, [
                'bedrooms' => $property->bedrooms,
                'property_type' => $property->property_type,
            ]));
        } catch (RuntimeException $e) {
            // 404: 11Done doesn't offer this service ID (wrong ELEVENDONE_SERVICE_* or switched off
            // in 11Done). The landlord can still book it; the team arranges it instead.
            if ($e->getCode() === 404 && config("lettingworld.service_prices.$type") !== null) {
                report($e);

                return $this->teamArranged($type);
            }

            return [
                'provider' => '11done', 'service_id' => $serviceId, 'price' => null, 'bookable' => false,
                'reason' => $e->getMessage(), 'min_date' => today()->addDays(3)->toDateString(), 'time_slots' => Booking::TIME_SLOTS,
            ];
        }

        $bookable = (bool) ($quote['bookable'] ?? false);

        return [
            'provider' => '11done',
            'service_id' => $serviceId,
            // No price shown for "priced on request" (11Done reports 0 there).
            'price' => $bookable && isset($quote['price']) ? (float) $quote['price'] : null,
            'bookable' => $bookable,
            'reason' => $quote['reason'] ?? null,
            'min_date' => $quote['min_date'] ?? today()->addDays(3)->toDateString(),
            'time_slots' => $quote['time_slots'] ?? Booking::TIME_SLOTS,
        ];
    }

    /** A renewal the Letting World team arranges at its own price. */
    private function teamArranged(string $type): array
    {
        $price = config("lettingworld.service_prices.$type");

        return [
            'provider' => 'letting_world',
            'service_id' => null,
            'price' => $price !== null ? (float) $price : null,
            'bookable' => $price !== null,
            'reason' => $price !== null ? null : Certificate::typeLabel($type).' can’t be booked online yet.',
            'min_date' => today()->addDays((int) config('lettingworld.manual_min_days'))->toDateString(),
            'time_slots' => Booking::TIME_SLOTS,
        ];
    }

    private function certificateFor(Property $property, string $type, ?string $certificateId): ?Certificate
    {
        $query = $property->certificates()->where('type', $type);

        return $certificateId
            ? $query->whereKey($certificateId)->first()
            : $query->orderByDesc('expiry_date')->first();
    }

    private function openBookingExists(User $landlord, int $propertyId, string $serviceType): bool
    {
        return $landlord->bookings()
            ->where('property_id', $propertyId)
            ->where('service_type', $serviceType)
            ->whereIn('status', Booking::OPEN_STATUSES)
            ->exists();
    }
}
