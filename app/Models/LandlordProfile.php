<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'whatsapp', 'address_line1', 'address_line2', 'city', 'postcode', 'landlord_type',
    'company_name', 'company_number', 'registration_number', 'properties_owned', 'experience',
    'management', 'preferred_contact', 'hear_about_us', 'terms_accepted_at',
    'marketing_consent', 'marketing_consent_at',
])]
class LandlordProfile extends Model
{
    protected function casts(): array
    {
        return [
            'properties_owned' => 'integer',
            'marketing_consent' => 'boolean',
            'terms_accepted_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
