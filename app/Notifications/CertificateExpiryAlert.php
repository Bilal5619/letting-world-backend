<?php

namespace App\Notifications;

use App\Models\Certificate;
use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Gas Safety Certificate expiring — 12 High Street, Glasgow expires on 15 December 2026." */
class CertificateExpiryAlert extends Notification
{
    use Queueable;

    public function __construct(public Certificate $certificate) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', ExpoPushChannel::class];
    }

    private function content(): array
    {
        $c = $this->certificate;
        $label = Certificate::typeLabel($c->type);
        $address = $c->property->shortAddress();
        $date = $c->expiry_date->format('j F Y');
        $expired = $c->daysUntilExpiry() < 0;

        return [
            'title' => $expired ? "$label expired" : "$label expiring",
            'body' => $expired
                ? "The $label for $address expired on $date. Book a renewal to stay compliant."
                : "The $label for $address expires on $date.",
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->content() + [
            'kind' => 'certificate_expiry',
            'action' => 'renew',
            'actionLabel' => 'Renew certificate',
            'propertyId' => (string) $this->certificate->property_id,
            'certificateId' => (string) $this->certificate->id,
            'certificateType' => $this->certificate->type,
            'expiryDate' => $this->certificate->expiry_date->toDateString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        ['title' => $title, 'body' => $body] = $this->content();

        return (new MailMessage)
            ->subject($title)
            ->greeting("Hi {$notifiable->first_name},")
            ->line($body)
            ->line('Open the Letting World app and tap "Renew Now" to book with one click — your property and payment details are already saved.')
            ->salutation('Letting World');
    }
}
