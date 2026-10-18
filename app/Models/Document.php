<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenancy_id', 'tenancy_agreement_id', 'category', 'title', 'shared_with_tenant', 'archived_at',
    'file_path', 'file_name', 'file_mime', 'file_size', 'uploaded_by',
])]
class Document extends Model
{
    protected function casts(): array
    {
        return [
            'shared_with_tenant' => 'boolean',
            'archived_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(TenancyAgreement::class, 'tenancy_agreement_id');
    }

    public function tenancy(): BelongsTo
    {
        return $this->belongsTo(Tenancy::class);
    }

    /**
     * Documents a tenant may see for one of their tenancies: shared property-wide documents,
     * plus shared documents attached to their own tenancy — never another tenant's, never
     * archived. Signed contracts are delivered through the tenant's own agreements instead.
     */
    public function scopeVisibleToTenancy(Builder $query, Tenancy $tenancy): void
    {
        $query->where('property_id', $tenancy->property_id)
            ->where('shared_with_tenant', true)
            ->whereNull('archived_at')
            ->whereNull('tenancy_agreement_id')
            ->where(fn (Builder $q) => $q->whereNull('tenancy_id')->orWhere('tenancy_id', $tenancy->id));
    }
}
