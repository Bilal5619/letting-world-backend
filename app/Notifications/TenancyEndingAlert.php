<?php

namespace App\Notifications;

use App\Models\Tenancy;
use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenancyEndingAlert extends Notification
{
    use Queueable;

    public function __construct(public Tenancy $tenancy) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', ExpoPushChannel::class];
    }

    private function content(): array
    {
        $t = $this->tenancy;
        $tenant = trim("{$t->tenant->first_name} {$t->tenant->last_name}");

        return [
            'title' => 'Tenancy agreement ending soon',
            'body' => "{$tenant}'s tenancy at {$t->property->shortAddress()} ends on {$t->end_date->format('j F Y')}.",
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->content() + [
            'kind' => 'tenancy_ending',
            'action' => 'view_tenant',
            'actionLabel' => 'View tenancy',
            'propertyId' => (string) $this->tenancy->property_id,
            'tenantId' => (string) $this->tenancy->tenant_id,
            'endDate' => $this->tenancy->end_date->toDateString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        ['title' => $title, 'body' => $body] = $this->content();

        return (new MailMessage)
            ->subject($title)
            ->greeting("Hi {$notifiable->first_name},")
            ->line($body)
            ->line('Open the Letting World app to renew the tenancy or plan the next let.')
            ->salutation('Letting World');
    }
}
