<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancels a renewal booking: in 11Done first (when it was handed over), then refunds the
 * landlord. Used by landlords in the app and by admins in the admin panel.
 */
class BookingCancellation
{
    public function __construct(private ElevenDone $elevenDone, private Payments $payments) {}

    /** @throws RuntimeException with a message that can be shown to the user */
    public function cancel(Booking $booking): Booking
    {
        if (! $booking->isCancellable()) {
            throw new RuntimeException('This booking can no longer be cancelled.');
        }

        if ($booking->sync_status === 'sent') {
            $data = ElevenDone::attempt(fn () => $this->elevenDone->cancel($booking->reference));
            if (($data['order_status'] ?? null) === 'completed') {
                throw new RuntimeException('The engineer has already completed this job, so it can’t be cancelled.');
            }
            $booking->provider_status = $data['order_status'] ?? $booking->provider_status;
        }

        DB::transaction(function () use ($booking) {
            // A webhook from 11Done may have cancelled + refunded it meanwhile; never refund twice.
            $current = Booking::lockForUpdate()->find($booking->id);
            if ($current->status === 'cancelled') {
                $booking->setRawAttributes($current->getAttributes(), true);

                return;
            }
            if ($booking->payment_status === 'paid' && $booking->payment_intent_id) {
                $this->payments->refund($booking->payment_intent_id);
                $booking->payment_status = 'refunded';
            }
            $booking->fill(['status' => 'cancelled', 'cancelled_at' => now()]);
            // Not yet sent: nothing to cancel in 11Done, and stop the scheduler sending it.
            if (in_array($booking->sync_status, ['pending', 'failed'], true)) {
                $booking->sync_status = 'not_required';
            }
            $booking->save();
        });

        return $booking;
    }
}
