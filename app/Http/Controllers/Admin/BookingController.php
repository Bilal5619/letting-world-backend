<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Booking;
use App\Models\Certificate;
use App\Models\PropertyEvent;
use App\Notifications\BookingUpdated;
use App\Support\BookingCancellation;
use App\Support\ElevenDone;
use App\Support\ElevenDoneSync;
use App\Support\PrivateFiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Renewal bookings. 11Done jobs are run in 11Done (here: watch, retry, refresh, cancel);
 * team-arranged jobs (services 11Done doesn't offer) are run here: assign a contractor,
 * then complete with the new certificate.
 */
class BookingController extends Controller
{
    private const TABS = ['attention', 'open', 'completed', 'cancelled', 'all'];

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'all';
        $base = Booking::query()
            ->when($request->query('provider'), fn ($q, $p) => $q->where('provider', $p === '11done' ? '11done' : 'letting_world'))
            ->when($request->query('service'), fn ($q, $s) => $q->where('service_type', $s))
            ->when(trim((string) $request->query('q')), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('reference', 'like', $like)->orWhere('provider_reference', 'like', $like)
                    ->orWhereHas('property', fn ($p) => $p->where('address_line1', 'like', $like)->orWhere('postcode', 'like', $like))
                    ->orWhereHas('landlord', fn ($l) => $l->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])->orWhere('email', 'like', $like)));
            });

        $counts = collect(self::TABS)->mapWithKeys(fn ($t) => [$t => $this->tab(clone $base, $t)->count()]);

        return view('admin.bookings.index', [
            'bookings' => $this->tab($base, $tab)->with('property', 'landlord')->latest()->paginate(20)->withQueryString(),
            'tab' => $tab,
            'counts' => $counts,
            'services' => Booking::distinct()->orderBy('service_type')->pluck('service_type'),
        ]);
    }

    public function show(Booking $booking): View
    {
        $booking->load('property.tenants', 'landlord.landlordProfile', 'certificate', 'resultCertificate');

        $timeline = PropertyEvent::with('user')->where('property_id', $booking->property_id)
            ->where('meta->bookingId', $booking->id)->get()
            ->map(fn (PropertyEvent $e) => ['at' => $e->created_at, 'title' => $e->title, 'text' => $e->description, 'who' => $e->user?->fullName()])
            ->concat(AdminAction::with('admin')->where('subject_type', 'booking')->where('subject_id', $booking->id)->get()
                ->map(fn (AdminAction $a) => ['at' => $a->created_at, 'title' => $a->description, 'text' => null, 'who' => $a->admin?->fullName().' (admin)']))
            ->sortByDesc('at')->values();

        return view('admin.bookings.show', [
            'booking' => $booking,
            'timeline' => $timeline,
            'slots' => Booking::TIME_SLOTS,
        ]);
    }

    public function notes(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['admin_notes' => ['nullable', 'string', 'max:5000']]);
        $booking->forceFill(['admin_notes' => $data['admin_notes']])->save();
        AdminAction::record('booking_notes', 'Updated internal notes', $booking);

        return back()->with('status', 'Notes saved.');
    }

    /** Team-arranged job: contractor + appointment. Notifies the landlord (and tenant). */
    public function schedule(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->isManual() && $booking->isCancellable(), 422, 'Only open team-arranged bookings can be scheduled here.');
        $data = $request->validate([
            'engineer_name' => ['required', 'string', 'max:120'],
            'scheduled_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'scheduled_slot' => ['required', Rule::in(Booking::TIME_SLOTS)],
        ], [], ['engineer_name' => 'contractor', 'scheduled_date' => 'date', 'scheduled_slot' => 'time slot']);

        $booking->forceFill($data + ['status' => 'confirmed'])->save();
        BookingUpdated::sendFor($booking->fresh(), 'confirmed');
        AdminAction::record('booking_scheduled', "Assigned {$data['engineer_name']} for ".date('j M Y', strtotime($data['scheduled_date']))." ({$data['scheduled_slot']})", $booking);

        return back()->with('status', 'Contractor assigned. The landlord has been notified.');
    }

    /** Team-arranged job done: the new certificate renews the property's certificate. */
    public function complete(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->isManual() && $booking->status === 'confirmed', 422, 'Assign a contractor before completing this job.');
        $data = $this->validateCertificate($request);

        DB::transaction(function () use ($booking, $data, $request) {
            $certificate = $this->storeCertificate($booking, $data, $request);
            $booking->forceFill(['status' => 'completed', 'completed_at' => now(), 'result_certificate_id' => $certificate->id])->save();
        });
        BookingUpdated::sendFor($booking->fresh(), 'completed');
        AdminAction::record('booking_completed', 'Completed the job and uploaded the new certificate', $booking);

        return back()->with('status', 'Job completed. The new certificate has been added to the property.');
    }

    /** A completed job whose certificate didn't come through (e.g. 11Done vendor forgot to upload it). */
    public function attachCertificate(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->status === 'completed' && ! $booking->result_certificate_id, 422, 'This booking already has its certificate.');
        $data = $this->validateCertificate($request);

        DB::transaction(function () use ($booking, $data, $request) {
            $certificate = $this->storeCertificate($booking, $data, $request);
            $booking->forceFill(['result_certificate_id' => $certificate->id])->save();
        });
        AdminAction::record('booking_certificate', 'Uploaded the certificate for a completed job', $booking);

        return back()->with('status', 'Certificate added to the property.');
    }

    public function cancel(Request $request, Booking $booking, BookingCancellation $cancellation): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $cancellation->cancel($booking);
        } catch (RuntimeException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        if (filled($data['reason'] ?? null)) {
            $booking->forceFill(['admin_notes' => trim($booking->admin_notes."\n\nCancelled by admin: ".$data['reason'])])->save();
        }
        BookingUpdated::sendFor($booking->fresh(), 'cancelled');
        AdminAction::record('booking_cancelled', 'Cancelled the booking'.($booking->payment_status === 'refunded' ? ' and refunded the landlord' : ''), $booking);

        return back()->with('status', 'Booking cancelled'.($booking->payment_status === 'refunded' ? ' and refunded.' : '.'));
    }

    /** Send to 11Done now instead of waiting for the 5-minute retry. */
    public function retry(Booking $booking, ElevenDoneSync $sync): RedirectResponse
    {
        abort_unless($booking->provider === '11done' && in_array($booking->sync_status, ['pending', 'failed'], true) && $booking->isCancellable(), 422);

        $sent = $sync->push($booking);
        AdminAction::record('booking_retry', $sent ? 'Sent the booking to 11Done' : 'Tried to send the booking to 11Done (failed)', $booking);

        return $sent
            ? back()->with('status', 'Sent to 11Done.')
            : back()->withErrors(['booking' => '11Done still couldn’t be reached: '.$booking->fresh()->sync_error]);
    }

    /** Pull the latest status / engineer / certificate from 11Done (in case a webhook was missed). */
    public function refresh(Booking $booking, ElevenDone $elevenDone, ElevenDoneSync $sync): RedirectResponse
    {
        abort_unless($booking->provider === '11done' && $booking->sync_status === 'sent', 422);

        try {
            $sync->apply($booking, ElevenDone::attempt(fn () => $elevenDone->booking($booking->reference)));
        } catch (RuntimeException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', 'Updated from 11Done.');
    }

    private function tab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'attention' => $query->whereIn('status', Booking::OPEN_STATUSES)->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('provider', 'letting_world')->where('status', 'requested'))
                ->orWhere('sync_status', 'failed')),
            'open' => $query->whereIn('status', Booking::OPEN_STATUSES),
            'completed' => $query->where('status', 'completed'),
            'cancelled' => $query->where('status', 'cancelled'),
            default => $query,
        };
    }

    private function validateCertificate(Request $request): array
    {
        return $request->validate([
            'certificate' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.config('lettingworld.upload_max_kb')],
            'issue_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'expiry_date' => ['nullable', 'date_format:Y-m-d', 'after:issue_date'],
            'provider_reg' => ['nullable', 'string', 'max:60'],
        ], [], ['provider_reg' => 'registration number']);
    }

    private function storeCertificate(Booking $booking, array $data, Request $request): Certificate
    {
        $expiry = $data['expiry_date'] ?? null;
        $months = config("lettingworld.certificate_types.{$booking->service_type}.months");
        if (! $expiry && $months) {
            $expiry = date('Y-m-d', strtotime($data['issue_date']." +$months months"));
        }

        return $booking->property->certificates()->create(PrivateFiles::store($request->file('certificate'), "certificates/{$booking->property_id}") + [
            'type' => $booking->service_type,
            'issue_date' => $data['issue_date'],
            'expiry_date' => $expiry,
            'provider_name' => $booking->engineer_name ?: ($booking->provider === '11done' ? '11Done' : 'Letting World'),
            'provider_reg' => $data['provider_reg'] ?? null,
            'notes' => "Added by Letting World for booking {$booking->reference}.",
        ]);
    }
}
