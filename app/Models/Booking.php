<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'property_id', 'certificate_id', 'result_certificate_id', 'service_type', 'preferred_date', 'scheduling',
    'time_slot', 'access', 'access_notes', 'notify_tenant', 'status', 'price', 'payment_status',
    'payment_intent_id', 'card_brand', 'card_last4', 'provider', 'provider_service_id', 'provider_reference',
    'provider_status', 'sync_status', 'sync_error', 'sync_attempts', 'synced_at', 'engineer_name',
    'scheduled_date', 'scheduled_slot', 'completed_at', 'cancelled_at', 'admin_notes',
])]
class Booking extends Model
{
    public const OPEN_STATUSES = ['requested', 'confirmed'];

    /** 11Done's 2-hour appointment windows; also used for jobs the Letting World team arranges. */
    public const TIME_SLOTS = ['08:00 AM - 10:00 AM', '10:00 AM - 12:00 PM', '02:00 PM - 04:00 PM', '04:00 PM - 06:00 PM'];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date:Y-m-d',
            'notify_tenant' => 'boolean',
            'price' => 'float',
            'cancelled_at' => 'datetime',
            'scheduled_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            do {
                $booking->reference = 'LW-'.Str::upper(Str::random(6));
            } while (static::where('reference', $booking->reference)->exists());
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    /** The new certificate added when the job was completed. */
    public function resultCertificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'result_certificate_id');
    }

    /** Jobs the Letting World team arranges itself (services 11Done doesn't offer). */
    public function isManual(): bool
    {
        return $this->provider !== '11done';
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
