<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'role', 'first_name', 'last_name', 'email', 'phone', 'password', 'auth_provider', 'google_id',
    'profile_completed_at', 'must_change_password', 'landlord_id', 'stripe_customer_id',
    'emergency_contact_name', 'emergency_contact_phone', 'last_login_at', 'email_verified_at',
    'suspended_at', 'suspended_reason',
])]
#[Hidden(['password', 'remember_token', 'google_id', 'stripe_customer_id'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_LANDLORD = 'landlord';

    public const ROLE_TENANT = 'tenant';

    /** Letting World staff — admin panel only, never the mobile app. */
    public const ROLE_ADMIN = 'admin';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'profile_completed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'suspended_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isLandlord(): bool
    {
        return $this->role === self::ROLE_LANDLORD;
    }

    public function isTenant(): bool
    {
        return $this->role === self::ROLE_TENANT;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** "BH" for avatars. */
    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    public function landlordProfile(): HasOne
    {
        return $this->hasOne(LandlordProfile::class);
    }

    /** Properties owned by this landlord. */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'landlord_id');
    }

    /** Tenant accounts created by this landlord. */
    public function tenants(): HasMany
    {
        return $this->hasMany(User::class, 'landlord_id')->where('role', self::ROLE_TENANT);
    }

    /** For tenants: the landlord who created the account. */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /** For tenants: one tenancy per property they rent. */
    public function tenancies(): HasMany
    {
        return $this->hasMany(Tenancy::class, 'tenant_id');
    }

    /** For tenants: the only properties they are allowed to see. */
    public function assignedProperties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'tenancies', 'tenant_id', 'property_id')
            ->withPivot('id');
    }

    public function pushTokens(): HasMany
    {
        return $this->hasMany(PushToken::class);
    }

    /** For tenants: the tenancy agreements they are a party to. */
    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(TenancyAgreement::class, 'tenancy_agreement_tenant', 'tenant_id', 'tenancy_agreement_id');
    }

    /** For landlords: renewal bookings. */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'landlord_id');
    }
}
