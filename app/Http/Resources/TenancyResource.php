<?php

namespace App\Http\Resources;

use App\Models\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenancy */
class TenancyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'propertyId' => (string) $this->property_id,
            'propertyAddress' => $this->whenLoaded('property', fn () => "{$this->property->address_line1}, {$this->property->postcode}"),
            'tenantId' => (string) $this->tenant_id,
            'tenantName' => $this->whenLoaded('tenant', fn () => trim("{$this->tenant->first_name} {$this->tenant->last_name}")),
            'startDate' => $this->start_date?->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'monthlyRent' => $this->monthly_rent,
            'rentDueDay' => $this->rent_due_day,
            'depositAmount' => $this->deposit_amount,
            'depositScheme' => $this->deposit_scheme,
        ];
    }
}
