<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the landlord when a tenant reports a repair. */
class MaintenanceReported extends Notification
{
    use Queueable;

    public function __construct(public MaintenanceRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', ExpoPushChannel::class];
    }

    private function content(): array
    {
        $m = $this->request;
        $who = $m->tenant ? trim("{$m->tenant->first_name} {$m->tenant->last_name}") : 'Your tenant';
        $urgent = in_array($m->priority, ['urgent', 'emergency'], true) ? strtoupper($m->priority).': ' : '';

        return [
            'title' => "{$urgent}New repair reported",
            'body' => "{$who} reported \"{$m->title}\" at {$m->property->shortAddress()}.",
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->content() + [
            'kind' => 'maintenance_reported',
            'action' => 'view_maintenance',
            'actionLabel' => 'View repair',
            'propertyId' => (string) $this->request->property_id,
            'maintenanceId' => (string) $this->request->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        ['title' => $title, 'body' => $body] = $this->content();

        return (new MailMessage)
            ->subject($title)
            ->greeting("Hi {$notifiable->first_name},")
            ->line($body)
            ->line('Open the Letting World app to see the details and photos, and update the tenant.')
            ->salutation('Letting World');
    }
}
