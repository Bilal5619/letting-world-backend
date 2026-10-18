<?php

namespace App\Notifications\Channels;

use App\Models\PushToken;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Phone push notifications through Expo's push service. Uses the notification's toArray()
 * (title, body + navigation data), so every in-app notification also pops up on the phone.
 * Users without a registered device are skipped.
 */
class ExpoPushChannel
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notifiable, 'pushTokens')) {
            return;
        }
        $tokens = $notifiable->pushTokens()->pluck('token');
        if ($tokens->isEmpty() || ! method_exists($notification, 'toArray')) {
            return;
        }

        $payload = $notification->toArray($notifiable);
        $messages = $tokens->map(fn (string $token) => [
            'to' => $token,
            'title' => $payload['title'] ?? 'Letting World',
            'body' => $payload['body'] ?? '',
            'sound' => 'default',
            'data' => Arr::except($payload, ['title', 'body']),
        ])->values()->all();

        try {
            $response = Http::timeout(8)->acceptJson()->post(self::ENDPOINT, $messages);
            // Forget phones that uninstalled the app or revoked permission.
            foreach ($response->json('data', []) as $i => $ticket) {
                if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($messages[$i])) {
                    PushToken::where('token', $messages[$i]['to'])->delete();
                }
            }
        } catch (Throwable $e) {
            report($e); // a push failure never blocks the in-app notification or email
        }
    }
}
