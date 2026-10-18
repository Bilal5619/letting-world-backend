<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use App\Models\Property;
use App\Models\SentAlert;
use App\Models\Tenancy;
use App\Notifications\CertificateExpiryAlert;
use App\Notifications\TenancyEndingAlert;
use App\Support\Compliance;
use Illuminate\Console\Command;

/**
 * Runs every morning (see routes/console.php). Sends each landlord:
 *  - certificate alerts at 60, 30, 14 and 7 days before expiry, on expiry, then weekly while expired;
 *  - tenancy alerts 60 and 30 days before a tenancy ends.
 * Each alert is recorded in sent_alerts so it is only sent once. Certificates with a renewal
 * already booked are skipped.
 */
class SendExpiryAlerts extends Command
{
    protected $signature = 'alerts:send-expiry';

    protected $description = 'Send certificate expiry and tenancy end alerts to landlords';

    public function handle(): int
    {
        $sent = 0;

        Property::query()->active()
            ->with(['certificates', 'openBookings', 'landlord'])
            ->chunkById(200, function ($properties) use (&$sent) {
                foreach ($properties as $property) {
                    foreach (Compliance::current($property) as $certificate) {
                        $key = $this->certificateAlertKey($certificate);
                        if ($key && SentAlert::claim($certificate, $key)) {
                            $property->landlord->notify(new CertificateExpiryAlert($certificate));
                            $sent++;
                        }
                    }
                }
            });

        Tenancy::query()
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [today(), today()->addDays(max(config('lettingworld.tenancy_end_alert_days')))])
            ->whereHas('property', fn ($q) => $q->whereNull('archived_at'))
            ->with(['property.landlord', 'tenant'])
            ->chunkById(200, function ($tenancies) use (&$sent) {
                foreach ($tenancies as $tenancy) {
                    $key = $this->windowKey((int) today()->diffInDays($tenancy->end_date, false), config('lettingworld.tenancy_end_alert_days'));
                    if ($key && SentAlert::claim($tenancy, $key)) {
                        $tenancy->property->landlord->notify(new TenancyEndingAlert($tenancy));
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} alert(s).");

        return self::SUCCESS;
    }

    /** Which alert (if any) is due for this certificate today. */
    private function certificateAlertKey(Certificate $certificate): ?string
    {
        $status = $certificate->status();
        if (! in_array($status, [Certificate::STATUS_EXPIRING, Certificate::STATUS_EXPIRED], true)) {
            return null; // valid, no expiry, or renewal already booked
        }

        $days = $certificate->daysUntilExpiry();
        if ($days < 0) {
            // "expired" on the first run after expiry, then "expired:1", "expired:2"… each week.
            $week = intdiv(-$days, config('lettingworld.expired_reminder_every_days'));

            return $week === 0 ? 'expired' : "expired:$week";
        }

        return $this->windowKey($days, config('lettingworld.alert_days'));
    }

    /**
     * The tightest threshold the date is inside, e.g. 25 days left with [60, 30, 14, 7] → "30".
     * If a run is missed, only the most relevant alert is sent (not 60 and 30 together).
     */
    private function windowKey(int $days, array $thresholds): ?string
    {
        $inside = array_filter($thresholds, fn (int $t) => $days <= $t);

        return $inside ? (string) min($inside) : null;
    }
}
