<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->isLandlord() ? $this->landlordProfile : null;

        return [
            'id' => (string) $this->id,
            'role' => $this->role,
            'email' => $this->email,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'phone' => $this->phone,
            'authProvider' => $this->auth_provider,
            'hasPassword' => $this->password !== null,
            'profileComplete' => $this->isTenant() || $this->profile_completed_at !== null,
            'mustChangePassword' => (bool) $this->must_change_password,
            'createdAt' => $this->created_at?->toIso8601String(),
            'landlordProfile' => $profile ? [
                'phone' => $this->phone,
                'whatsapp' => $profile->whatsapp,
                'addressLine1' => $profile->address_line1,
                'addressLine2' => $profile->address_line2,
                'city' => $profile->city,
                'postcode' => $profile->postcode,
                'landlordType' => $profile->landlord_type,
                'companyName' => $profile->company_name,
                'companyNumber' => $profile->company_number,
                'registrationNumber' => $profile->registration_number,
                'propertiesOwned' => $profile->properties_owned,
                'experience' => $profile->experience,
                'management' => $profile->management,
                'preferredContact' => $profile->preferred_contact,
                'hearAboutUs' => $profile->hear_about_us,
                'consentTerms' => $profile->terms_accepted_at !== null,
                'consentMarketing' => $profile->marketing_consent,
            ] : null,
            'emergencyContactName' => $this->when($this->isTenant(), $this->emergency_contact_name),
            'emergencyContactPhone' => $this->when($this->isTenant(), $this->emergency_contact_phone),
        ];
    }
}
