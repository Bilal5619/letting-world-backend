<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** The tenancy contract: one property, one or more tenants, dates, rent and deposit. */
#[Fillable([
    'start_date', 'end_date', 'monthly_rent', 'rent_due_day', 'deposit_amount',
    'deposit_scheme', 'deposit_reference', 'notes',
])]
class TenancyAgreement extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'monthly_rent' => 'float',
            'deposit_amount' => 'float',
            'rent_due_day' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenancy_agreement_tenant', 'tenancy_agreement_id', 'tenant_id');
    }

    /** The signed contract file (latest upload). */
    public function contract(): HasOne
    {
        return $this->hasOne(Document::class)->whereNull('archived_at')->latestOfMany();
    }

    /** upcoming / active / ended */
    public function status(): string
    {
        if ($this->start_date->isFuture()) {
            return 'upcoming';
        }

        return $this->end_date && $this->end_date->isPast() && ! $this->end_date->isToday() ? 'ended' : 'active';
    }
}
