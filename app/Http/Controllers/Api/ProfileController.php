<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Support\Uk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    private const ATTRIBUTES = [
        'firstName' => 'first name',
        'lastName' => 'last name',
        'phone' => 'phone number',
        'whatsapp' => 'WhatsApp number',
        'addressLine1' => 'address',
        'addressLine2' => 'address line 2',
        'city' => 'town / city',
        'postcode' => 'postcode',
        'landlordType' => 'landlord type',
        'companyName' => 'company name',
        'companyNumber' => 'company number',
        'registrationNumber' => 'registration number',
        'propertiesOwned' => 'number of properties',
        'experience' => 'experience level',
        'management' => 'management type',
        'preferredContact' => 'preferred contact method',
        'emergencyContactName' => 'emergency contact name',
        'emergencyContactPhone' => 'emergency contact phone',
    ];

    private const MESSAGES = [
        'phone.regex' => 'Enter a valid UK phone number.',
        'whatsapp.regex' => 'Enter a valid UK phone number.',
        'emergencyContactPhone.regex' => 'Enter a valid UK phone number.',
        'postcode.regex' => 'Enter a valid UK postcode.',
        'consentTerms.accepted' => 'You must accept the Terms and Privacy Policy.',
        'companyName.required_if' => 'Company name is required for a limited company.',
        'companyNumber.required_if' => 'Company number is required for a limited company.',
    ];

    public function update(Request $request): UserResource
    {
        $user = $request->user();

        $request->merge([
            'phone' => Uk::normalisePhone($request->input('phone')),
            'whatsapp' => Uk::normalisePhone($request->input('whatsapp')),
            'emergencyContactPhone' => Uk::normalisePhone($request->input('emergencyContactPhone')),
            'postcode' => Uk::formatPostcode($request->input('postcode')),
        ]);

        $user->isLandlord() ? $this->updateLandlord($request, $user) : $this->updateTenant($request, $user);

        return new UserResource($user->fresh()->load('landlordProfile'));
    }

    /** Every field marked "required" is mandatory; completing them unlocks the app. */
    private function updateLandlord(Request $request, $user): void
    {
        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:'.Uk::PHONE_REGEX],
            'whatsapp' => ['nullable', 'regex:'.Uk::PHONE_REGEX],
            'addressLine1' => ['required', 'string', 'max:255'],
            'addressLine2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'postcode' => ['required', 'regex:'.Uk::POSTCODE_REGEX],
            'landlordType' => ['required', Rule::in(['individual', 'joint', 'company'])],
            'companyName' => ['nullable', 'required_if:landlordType,company', 'string', 'max:255'],
            'companyNumber' => ['nullable', 'required_if:landlordType,company', 'string', 'max:20'],
            'registrationNumber' => ['nullable', 'string', 'max:100'],
            'propertiesOwned' => ['required', 'integer', 'min:1', 'max:10000'],
            'experience' => ['required', Rule::in(['first_time', 'experienced', 'portfolio'])],
            'management' => ['required', Rule::in(['self', 'agent'])],
            'preferredContact' => ['required', Rule::in(['email', 'phone', 'whatsapp', 'sms'])],
            'hearAboutUs' => ['nullable', 'string', 'max:255'],
            'consentTerms' => ['accepted'],
            'consentMarketing' => ['boolean'],
        ], self::MESSAGES, self::ATTRIBUTES);

        DB::transaction(function () use ($user, $data) {
            $isCompany = $data['landlordType'] === 'company';
            $existing = $user->landlordProfile;
            $marketing = (bool) ($data['consentMarketing'] ?? false);

            $user->landlordProfile()->updateOrCreate([], [
                'whatsapp' => $data['whatsapp'] ?? null,
                'address_line1' => trim($data['addressLine1']),
                'address_line2' => $data['addressLine2'] ?? null,
                'city' => trim($data['city']),
                'postcode' => $data['postcode'],
                'landlord_type' => $data['landlordType'],
                'company_name' => $isCompany ? $data['companyName'] : null,
                'company_number' => $isCompany ? $data['companyNumber'] : null,
                'registration_number' => $data['registrationNumber'] ?? null,
                'properties_owned' => $data['propertiesOwned'],
                'experience' => $data['experience'],
                'management' => $data['management'],
                'preferred_contact' => $data['preferredContact'],
                'hear_about_us' => $data['hearAboutUs'] ?? null,
                // GDPR: keep the original consent timestamps.
                'terms_accepted_at' => $existing?->terms_accepted_at ?? now(),
                'marketing_consent' => $marketing,
                'marketing_consent_at' => $marketing ? ($existing?->marketing_consent_at ?? now()) : null,
            ]);

            $user->forceFill([
                'first_name' => trim($data['firstName']),
                'last_name' => trim($data['lastName']),
                'phone' => $data['phone'],
                'profile_completed_at' => $user->profile_completed_at ?? now(),
            ])->save();
        });
    }

    private function updateTenant(Request $request, $user): void
    {
        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'regex:'.Uk::PHONE_REGEX],
            'emergencyContactName' => ['nullable', 'string', 'max:255'],
            'emergencyContactPhone' => ['nullable', 'regex:'.Uk::PHONE_REGEX],
        ], self::MESSAGES, self::ATTRIBUTES);

        $user->forceFill([
            'first_name' => trim($data['firstName']),
            'last_name' => trim($data['lastName']),
            'phone' => $data['phone'] ?? null,
            'emergency_contact_name' => $data['emergencyContactName'] ?? null,
            'emergency_contact_phone' => $data['emergencyContactPhone'] ?? null,
        ])->save();
    }
}
