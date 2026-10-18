<?php

namespace App\Http\Resources;

use App\Models\Property;
use App\Support\Compliance;
use App\Support\PrivateFiles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Property */
class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'reference' => $this->reference,
            'addressLine1' => $this->address_line1,
            'addressLine2' => $this->address_line2,
            'city' => $this->city,
            'postcode' => $this->postcode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'propertyType' => $this->property_type,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'furnishing' => $this->furnishing,
            'hasGas' => $this->has_gas,
            'monthlyRent' => $this->monthly_rent,
            // The landlord's private notes are never sent to tenants.
            'notes' => $this->when(! $request->user()?->isTenant(), $this->notes),
            'photoUrl' => $this->photo_path ? PrivateFiles::signedUrl('property', $this->id) : null,
            'status' => $this->status,
            'archived' => $this->archived_at !== null,
            'tenantCount' => $this->whenCounted('tenancies'),
            'compliance' => $this->whenLoaded('certificates', fn () => Compliance::summary($this->resource)),
            'missingCertificates' => $this->whenLoaded('certificates', fn () => Compliance::missing($this->resource)),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
