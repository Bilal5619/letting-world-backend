<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Certificate;
use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Lets the tenant know an engineer is coming (only when the landlord ticked "notify tenant"). */
class TenantAppointmentNotice extends Notification
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
        // Older bookings used morning/afternoon/any; 11Done bookings use a 2-hour window.
        $slot = match ($b->time_slot) {
            'morning' => ' in the morning',
            'afternoon' => ' in the afternoon',
            null, '', 'any' => '',
            default => " between {$b->time_slot}",
        };
        $label = Certificate::typeLabel($b->service_type);
        // "an EICR", "an EPC", "a Gas Safety…" — by sound, so initialisms starting with E/F/… take "an".
        $article = preg_match('/^([AEIOU]|[FHLMNRSX][A-Z])/', $label) ? 'An' : 'A';

        return [
            'title' => 'Engineer visit booked',
            'body' => sprintf(
                '%s %s visit is booked at %s on %s%s.%s',
                $article,
                $label,
                $b->property->shortAddress(),
                $b->preferred_date->format('j F Y'),
                $slot,
                $b->access === 'tenant' ? ' Please make sure someone is home to let the engineer in.' : '',
            ),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->content() + [
            'kind' => 'tenant_appointment',
            'action' => 'view_property',
            'actionLabel' => 'View property',
            'propertyId' => (string) $this->booking->property_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        ['title' => $title, 'body' => $body] = $this->content();

        return (new MailMessage)
            ->subject($title)
            ->greeting("Hi {$notifiable->first_name},")
            ->line($body)
            ->salutation('Letting World');
    }
}
