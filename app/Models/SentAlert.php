<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Record that an automatic alert went out, so it is never sent twice. */
#[Fillable(['subject_type', 'subject_id', 'alert_key', 'sent_at'])]
class SentAlert extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Claims the alert; returns false if it was already sent. Safe if two runs overlap. */
    public static function claim(Model $subject, string $key): bool
    {
        return static::query()->insertOrIgnore([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'alert_key' => $key,
            'sent_at' => now(),
        ]) === 1;
    }
}
