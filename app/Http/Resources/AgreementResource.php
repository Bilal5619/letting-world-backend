<?php

namespace App\Http\Resources;

use App\Models\TenancyAgreement;
use App\Models\User;
use App\Support\PrivateFiles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TenancyAgreement */
class AgreementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'propertyId' => (string) $this->property_id,
            'propertyAddress' => $this->whenLoaded('property', fn () => $this->property->shortAddress()),
            'startDate' => $this->start_date->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'monthlyRent' => $this->monthly_rent,
            'rentDueDay' => $this->rent_due_day,
            'depositAmount' => $this->deposit_amount,
            'depositScheme' => $this->deposit_scheme,
            'depositReference' => $this->deposit_reference,
            'notes' => $this->notes,
            'status' => $this->status(),
            // Names only — the people named on the contract.
            'tenants' => $this->whenLoaded('tenants', fn () => $this->tenants->map(fn (User $t) => [
                'id' => (string) $t->id,
                'name' => trim("{$t->first_name} {$t->last_name}"),
            ])->values()),
            'contract' => $this->whenLoaded('contract', fn () => $this->contract ? [
                'documentId' => (string) $this->contract->id,
                'name' => $this->contract->file_name,
                'mime' => $this->contract->file_mime,
                'size' => $this->contract->file_size,
                'url' => PrivateFiles::signedUrl('document', $this->contract->id),
                'uploadedAt' => $this->contract->created_at->toIso8601String(),
            ] : null),
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
