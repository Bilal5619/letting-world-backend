<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Audit log: one thing an admin did in the admin panel. */
#[Fillable(['admin_id', 'action', 'subject_type', 'subject_id', 'description', 'ip_address'])]
class AdminAction extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /** Records an action by the signed-in admin. */
    public static function record(string $action, string $description, ?Model $subject = null): self
    {
        return self::create([
            'admin_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? strtolower(class_basename($subject)) : null,
            'subject_id' => $subject?->getKey(),
            'description' => mb_substr($description, 0, 255),
            'ip_address' => request()->ip(),
        ]);
    }
}
