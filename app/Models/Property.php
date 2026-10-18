<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference', 'address_line1', 'address_line2', 'city', 'postcode', 'latitude', 'longitude',
    'property_type', 'bedrooms', 'bathrooms', 'furnishing', 'has_gas', 'monthly_rent', 'notes',
    'photo_path', 'photo_mime', 'status', 'archived_at',
])]
class Property extends Model
{
    protected function casts(): array
    {
        return [
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'has_gas' => 'boolean',
            'monthly_rent' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'archived_at' => 'datetime',
        ];
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function tenancies(): HasMany
    {
        return $this->hasMany(Tenancy::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenancies', 'property_id', 'tenant_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(TenancyAgreement::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PropertyEvent::class);
    }

    /** Renewal bookings still in progress — their certificates show "Renewal booked". */
    public function openBookings(): HasMany
    {
        return $this->hasMany(Booking::class)->whereIn('status', Booking::OPEN_STATUSES);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function fullAddress(): string
    {
        return collect([$this->address_line1, $this->address_line2, $this->city, $this->postcode])
            ->filter()->implode(', ');
    }

    /** e.g. "12 High Street, Glasgow" — the name used in alerts and bookings. */
    public function shortAddress(): string
    {
        return "{$this->address_line1}, {$this->city}";
    }
}
