<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Certificate;
use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifier;

/**
 * Progress on a renewal (from 11Done, or from the Letting World team for jobs it arranges):
 * engineer assigned, job completed (new certificate), or cancelled.
 */
class BookingUpdated extends Notification
{
    use Queueable;

    public function __construct(public Booking $booking, public string $change) {}

    /** Tells the landlord, and the tenants if they were kept informed or chose the time themselves. */
    public static function sendFor(Booking $booking, string $change): void
    {
        $booking->loadMissing('property.landlord', 'property.tenants');
        $booking->property->landlord->notify(new self($booking, $change));

        if (($booking->notify_tenant || $booking->scheduling === 'tenant') && $booking->property->tenants->isNotEmpty()) {
            Notifier::send($booking->property->tenants, new self($booking, $change));
        }
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', ExpoPushChannel::class];
    }

    private function content(object $notifiable): array
    {
        $b = $this->booking;
        $isLandlord = $notifiable->id === $b->landlord_id;
        $label = Certificate::typeLabel($b->service_type);
        $where = $b->property->shortAddress();
        $when = $b->scheduled_date
            ? ' on '.$b->scheduled_date->format('j F Y').($b->scheduled_slot ? " ({$b->scheduled_slot})" : '')
            : '';

        return match ($this->change) {
            'confirmed' => [
                'title' => 'Engineer assigned',
                'body' => trim(($b->engineer_name ?: 'An engineer')." will carry out the $label at $where$when.")
                    .(! $isLandlord && $b->access === 'tenant' ? ' Please make sure someone is home to let the engineer in.' : ''),
            ],
            'completed' => [
                'title' => "$label renewed",
                'body' => "The $label at $where is complete. The new certificate has been added to the property.",
            ],
            'cancelled' => $isLandlord ? [
                'title' => 'Booking cancelled',
                'body' => "The $label booking at $where (ref {$b->reference}) has been cancelled."
                    .($b->payment_status === 'refunded' ? ' Your payment has been refunded.' : ''),
            ] : [
                'title' => 'Engineer visit cancelled',
                'body' => "The $label visit at $where has been cancelled. Your landlord will arrange a new one.",
            ],
            default => [
                'title' => 'Booking updated',
                'body' => "Your $label booking at $where has been updated.",
            ],
        };
    }

    public function toArray(object $notifiable): array
    {
        return $this->content($notifiable) + [
            'kind' => 'booking_updated',
            'action' => 'view_property',
            'actionLabel' => 'View booking',
            'propertyId' => (string) $this->booking->property_id,
            'bookingId' => (string) $this->booking->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        ['title' => $title, 'body' => $body] = $this->content($notifiable);

        return (new MailMessage)
            ->subject("$title — {$this->booking->reference}")
            ->greeting("Hi {$notifiable->first_name},")
            ->line($body)
            ->salutation('Letting World');
    }
}
