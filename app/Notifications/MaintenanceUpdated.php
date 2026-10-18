<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceUpdate;
use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A status change or note on a repair — sent to the other party. */
class MaintenanceUpdated extends Notification
{
    use Queueable;

    public function __construct(public MaintenanceRequest $request, public MaintenanceUpdate $update) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', ExpoPushChannel::class];
    }

    private function content(): array
    {
        $m = $this->request;
        $status = $this->update->status ? str_replace('_', ' ', $this->update->status) : null;

        return [
            'title' => $status ? "Repair {$status}: {$m->title}" : "New message on repair: {$m->title}",
            'body' => $this->update->note
                ? mb_strimwidth($this->update->note, 0, 160, '…')
                : "The repair at {$m->property->shortAddress()} is now {$status}.",
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->content() + [
            'kind' => 'maintenance_updated',
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
            ->salutation('Letting World');
    }
}
