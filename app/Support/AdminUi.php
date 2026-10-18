<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Labels, tones and icons for statuses in the admin panel, plus formatting helpers.
 * Every status is shown as icon + label (never colour alone).
 *
 * Tones map to CSS tokens: ok, warn, bad, info, neutral, violet.
 */
class AdminUi
{
    /** @return array{label: string, tone: string, icon: string} */
    public static function bookingStatus(string $status): array
    {
        return match ($status) {
            'requested' => ['label' => 'Requested', 'tone' => 'info', 'icon' => 'hourglass'],
            'confirmed' => ['label' => 'Confirmed', 'tone' => 'ok', 'icon' => 'calendar-check'],
            'completed' => ['label' => 'Completed', 'tone' => 'neutral', 'icon' => 'badge-check'],
            'cancelled' => ['label' => 'Cancelled', 'tone' => 'neutral', 'icon' => 'ban'],
            default => ['label' => ucfirst($status), 'tone' => 'neutral', 'icon' => 'circle-dot'],
        };
    }

    public static function paymentStatus(?string $status): array
    {
        return match ($status) {
            'paid' => ['label' => 'Paid', 'tone' => 'ok', 'icon' => 'circle-check'],
            'refunded' => ['label' => 'Refunded', 'tone' => 'violet', 'icon' => 'rotate-ccw'],
            'failed' => ['label' => 'Failed', 'tone' => 'bad', 'icon' => 'circle-x'],
            'pending' => ['label' => 'Pending', 'tone' => 'warn', 'icon' => 'clock'],
            default => ['label' => 'Not charged', 'tone' => 'neutral', 'icon' => 'circle-dot'],
        };
    }

    /** Hand-over to 11Done; null for jobs the team arranges itself. */
    public static function syncStatus(Booking $booking): ?array
    {
        if ($booking->provider !== '11done') {
            return null;
        }

        return match ($booking->sync_status) {
            'sent' => ['label' => 'In 11Done', 'tone' => 'ok', 'icon' => 'plug-zap'],
            'failed' => ['label' => 'Send failed', 'tone' => 'bad', 'icon' => 'unplug'],
            'pending' => ['label' => 'Sending', 'tone' => 'warn', 'icon' => 'loader-circle'],
            default => null, // booked before 11Done was connected
        };
    }

    public static function provider(Booking $booking): array
    {
        return $booking->provider === '11done'
            ? ['label' => '11Done', 'tone' => 'info', 'icon' => 'plug-zap']
            : ['label' => 'Team arranged', 'tone' => 'violet', 'icon' => 'hard-hat'];
    }

    public static function certificateStatus(string $status): array
    {
        return match ($status) {
            'valid' => ['label' => 'Valid', 'tone' => 'ok', 'icon' => 'circle-check'],
            'no_expiry' => ['label' => 'On file', 'tone' => 'ok', 'icon' => 'circle-check'],
            'expiring' => ['label' => 'Expiring soon', 'tone' => 'warn', 'icon' => 'clock'],
            'expired' => ['label' => 'Expired', 'tone' => 'bad', 'icon' => 'triangle-alert'],
            'renewal_booked' => ['label' => 'Renewal booked', 'tone' => 'info', 'icon' => 'calendar-check'],
            'missing' => ['label' => 'Missing', 'tone' => 'violet', 'icon' => 'shield-alert'],
            default => ['label' => ucfirst($status), 'tone' => 'neutral', 'icon' => 'circle-dot'],
        };
    }

    public static function maintenanceStatus(string $status): array
    {
        return match ($status) {
            'reported' => ['label' => 'Reported', 'tone' => 'warn', 'icon' => 'inbox'],
            'in_progress' => ['label' => 'In progress', 'tone' => 'info', 'icon' => 'wrench'],
            'scheduled' => ['label' => 'Scheduled', 'tone' => 'info', 'icon' => 'calendar'],
            'resolved' => ['label' => 'Resolved', 'tone' => 'ok', 'icon' => 'circle-check'],
            'closed' => ['label' => 'Closed', 'tone' => 'neutral', 'icon' => 'check'],
            default => ['label' => ucfirst($status), 'tone' => 'neutral', 'icon' => 'circle-dot'],
        };
    }

    public static function priority(string $priority): array
    {
        return match ($priority) {
            'emergency' => ['label' => 'Emergency', 'tone' => 'bad', 'icon' => 'siren'],
            'urgent' => ['label' => 'Urgent', 'tone' => 'warn', 'icon' => 'triangle-alert'],
            'low' => ['label' => 'Low', 'tone' => 'neutral', 'icon' => 'circle-dot'],
            default => ['label' => 'Normal', 'tone' => 'neutral', 'icon' => 'circle-dot'],
        };
    }

    /** Account state for landlords and tenants. */
    public static function accountStatus(User $user): array
    {
        if ($user->isSuspended()) {
            return ['label' => 'Suspended', 'tone' => 'bad', 'icon' => 'ban'];
        }
        if ($user->isLandlord() && ! $user->profile_completed_at) {
            return ['label' => 'Profile incomplete', 'tone' => 'warn', 'icon' => 'clock'];
        }
        if ($user->isTenant() && $user->must_change_password) {
            return ['label' => 'Invited', 'tone' => 'info', 'icon' => 'mail'];
        }

        return ['label' => 'Active', 'tone' => 'ok', 'icon' => 'circle-check'];
    }

    public static function money(?float $amount, bool $pence = true): string
    {
        if ($amount === null) {
            return '—';
        }

        return '£'.number_format($amount, $pence && fmod($amount, 1.0) !== 0.0 ? 2 : 0);
    }

    /** 1,284 · 12.9K · 4.2M */
    public static function compact(float|int $n): string
    {
        return match (true) {
            abs($n) >= 1_000_000 => rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.').'M',
            abs($n) >= 10_000 => rtrim(rtrim(number_format($n / 1_000, 1), '0'), '.').'K',
            default => number_format($n),
        };
    }

    public static function date(Carbon|string|null $date): string
    {
        return $date ? Carbon::parse($date)->format('j M Y') : '—';
    }

    public static function dateTime(Carbon|string|null $date): string
    {
        return $date ? self::local($date)->format('j M Y, H:i') : '—';
    }

    /** Times are stored in UTC; the team works in UK time. */
    public static function local(Carbon|string|null $date = null): Carbon
    {
        return Carbon::parse($date ?? now())->setTimezone(config('lettingworld.display_timezone'));
    }

    /** "in 12 days" / "3 days ago" / "today" for an expiry or appointment date. */
    public static function days(?int $days): string
    {
        return match (true) {
            $days === null => '',
            $days === 0 => 'today',
            $days === 1 => 'tomorrow',
            $days === -1 => 'yesterday',
            $days > 0 => "in $days days",
            default => abs($days).' days ago',
        };
    }

    /** Masks secrets for display: "lw_dev_…9f2a". */
    public static function mask(?string $secret): string
    {
        if (! $secret) {
            return 'Not set';
        }

        return mb_substr($secret, 0, 6).'…'.mb_substr($secret, -4);
    }
}
