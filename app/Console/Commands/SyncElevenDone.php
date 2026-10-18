<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\ElevenDone;
use App\Support\ElevenDoneSync;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Safety net for the 11Done connection (runs every 5 minutes):
 *  - sends paid bookings that couldn't reach 11Done yet;
 *  - refreshes open bookings from 11Done in case a webhook was missed;
 *  - retries attaching a completed job's certificate if the download failed.
 */
class SyncElevenDone extends Command
{
    protected $signature = 'elevendone:sync';

    protected $description = 'Send pending bookings to 11Done and refresh their status';

    public function handle(ElevenDone $elevenDone, ElevenDoneSync $sync): int
    {
        if (! $elevenDone->enabled()) {
            $this->info('11Done is not connected — nothing to do.');

            return self::SUCCESS;
        }

        $sent = 0;
        Booking::whereIn('sync_status', ['pending', 'failed'])
            ->where('sync_attempts', '<', 50)
            ->whereIn('status', Booking::OPEN_STATUSES)
            ->get()
            ->each(function (Booking $b) use ($sync, &$sent) {
                $sent += (int) $sync->push($b);
            });

        $refreshed = 0;
        Booking::where('sync_status', 'sent')
            ->where(fn ($q) => $q->whereIn('status', Booking::OPEN_STATUSES)
                ->orWhere(fn ($c) => $c->where('status', 'completed')->whereNull('result_certificate_id')))
            ->get()
            ->each(function (Booking $b) use ($elevenDone, $sync, &$refreshed) {
                try {
                    $sync->apply($b, ElevenDone::attempt(fn () => $elevenDone->booking($b->reference)));
                    $refreshed++;
                } catch (RuntimeException $e) {
                    $this->warn("{$b->reference}: {$e->getMessage()}");
                }
            });

        $this->info("Sent {$sent} booking(s), refreshed {$refreshed}.");

        return self::SUCCESS;
    }
}
