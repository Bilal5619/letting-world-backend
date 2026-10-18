<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'type', 'issue_date', 'expiry_date', 'provider_name', 'provider_reg', 'notes',
    'file_path', 'file_name', 'file_mime', 'file_size', 'uploaded_by',
])]
class Certificate extends Model
{
    public const STATUS_VALID = 'valid';

    public const STATUS_EXPIRING = 'expiring';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_NO_EXPIRY = 'no_expiry';

    public const STATUS_RENEWAL_BOOKED = 'renewal_booked';

    protected function casts(): array
    {
        return [
            'issue_date' => 'date:Y-m-d',
            'expiry_date' => 'date:Y-m-d',
            'file_size' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Only the latest certificate of each type per property (a renewed one replaces the old one),
     * matching Compliance::current(): latest expiry wins, no expiry counts as latest.
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('certificates as newer')
            ->whereColumn('newer.property_id', 'certificates.property_id')
            ->whereColumn('newer.type', 'certificates.type')
            ->whereRaw("(COALESCE(newer.expiry_date, '9999-12-31') > COALESCE(certificates.expiry_date, '9999-12-31')
                OR (COALESCE(newer.expiry_date, '9999-12-31') = COALESCE(certificates.expiry_date, '9999-12-31') AND newer.id > certificates.id))"));
    }

    /** Certificates whose renewal hasn't been booked yet. */
    public function scopeWithoutOpenBooking(Builder $query): void
    {
        $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('bookings')
            ->whereColumn('bookings.property_id', 'certificates.property_id')
            ->whereColumn('bookings.service_type', 'certificates.type')
            ->whereIn('bookings.status', Booking::OPEN_STATUSES));
    }

    /** Certificates with a renewal booking in progress. */
    public function scopeWithOpenBooking(Builder $query): void
    {
        $query->whereExists(fn ($q) => $q->selectRaw('1')->from('bookings')
            ->whereColumn('bookings.property_id', 'certificates.property_id')
            ->whereColumn('bookings.service_type', 'certificates.type')
            ->whereIn('bookings.status', Booking::OPEN_STATUSES));
    }

    /** Certificates on properties that aren't archived. */
    public function scopeOnActiveProperties(Builder $query): void
    {
        $query->whereHas('property', fn ($q) => $q->whereNull('archived_at'));
    }

    /**
     * Valid / Expiring soon / Expired from the expiry date, or "Renewal booked" when an
     * expiring or expired certificate already has a renewal booking in progress.
     */
    public function status(): string
    {
        $status = $this->expiryStatus();

        return in_array($status, [self::STATUS_EXPIRING, self::STATUS_EXPIRED], true) && $this->isRenewalBooked()
            ? self::STATUS_RENEWAL_BOOKED
            : $status;
    }

    /** Status from the dates alone. */
    public function expiryStatus(): string
    {
        if (! $this->expiry_date) {
            return self::STATUS_NO_EXPIRY;
        }
        $days = $this->daysUntilExpiry();
        if ($days < 0) {
            return self::STATUS_EXPIRED;
        }

        return $days <= config('lettingworld.expiring_soon_days') ? self::STATUS_EXPIRING : self::STATUS_VALID;
    }

    /** Uses property.openBookings when eager-loaded, otherwise asks the database. */
    public function isRenewalBooked(): bool
    {
        if ($this->relationLoaded('property') && $this->property->relationLoaded('openBookings')) {
            return $this->property->openBookings->contains('service_type', $this->type);
        }

        return Booking::where('property_id', $this->property_id)
            ->where('service_type', $this->type)
            ->whereIn('status', Booking::OPEN_STATUSES)
            ->exists();
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->expiry_date
            ? (int) Carbon::today()->diffInDays($this->expiry_date->copy()->startOfDay(), false)
            : null;
    }

    public static function typeLabel(string $type): string
    {
        return config("lettingworld.certificate_types.$type.label", $type);
    }
}
