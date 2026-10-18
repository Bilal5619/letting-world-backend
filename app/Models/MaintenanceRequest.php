<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'reported_by', 'title', 'description', 'category', 'priority', 'status', 'resolved_at'])]
class MaintenanceRequest extends Model
{
    public const OPEN_STATUSES = ['reported', 'in_progress', 'scheduled'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MaintenancePhoto::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(MaintenanceUpdate::class)->oldest();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public static function categoryLabel(string $category): string
    {
        return config("lettingworld.maintenance_categories.$category", ucfirst($category));
    }
}
