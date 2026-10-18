<?php

namespace App\Models;

use App\Support\PrivateFiles;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'property_id', 'tenant_id', 'start_date', 'end_date', 'monthly_rent', 'rent_due_day',
    'deposit_amount', 'deposit_scheme',
])]
class Tenancy extends Model
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

    protected static function booted(): void
    {
        // Tenancy documents (ID, references, the agreement) belong to that tenant only —
        // delete them rather than letting them become visible property-wide.
        static::deleting(function (Tenancy $tenancy) {
            $tenancy->documents->each(function (Document $document) {
                PrivateFiles::delete($document->file_path);
                $document->delete();
            });
        });
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
