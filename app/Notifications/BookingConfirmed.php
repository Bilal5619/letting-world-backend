<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Certificate;
use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the landlord after a one-click renewal. */
class BookingConfirmed extends Notification
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', ExpoPushChannel::class];
    }

    private function content(): array
    {
        $b = $this->booking;
        $label = Certificate::typeLabel($b->service_type);
        $paid = $b->payment_status === 'paid'
            ? sprintf(' £%.2f was charged to your %s ending %s.', $b->price, ucfirst((string) $b->card_brand), $b->card_last4)
            : '';

        return [
            'title' => 'Booking confirmed',
            'body' => "Your $label renewal at {$b->property->shortAddress()} is booked"
                .($b->preferred_date
                    ? " for {$b->preferred_date->format('j F Y')}".($b->time_slot ? " ({$b->time_slot})" : '')
                    : '. Your tenant will get a link from 11Done to choose a time that suits them')
                ." (ref {$b->reference}).$paid",
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->content() + [
            'kind' => 'booking_confirmed',
            'action' => 'view_property',
            'actionLabel' => 'View booking',
            'propertyId' => (string) $this->booking->property_id,
            'bookingId' => (string) $this->booking->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        ['title' => $title, 'body' => $body] = $this->content();

        return (new MailMessage)
            ->subject("$title — {$this->booking->reference}")
            ->greeting("Hi {$notifiable->first_name},")
            ->line($body)
            ->line('11Done will confirm your engineer and exact arrival time.')
            ->salutation('Letting World');
    }
}
