<?php

namespace App\Http\Resources;

use App\Models\Certificate;
use App\Support\PrivateFiles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Certificate */
class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'propertyId' => (string) $this->property_id,
            'propertyAddress' => $this->whenLoaded('property', fn () => "{$this->property->address_line1}, {$this->property->postcode}"),
            'type' => $this->type,
            'typeLabel' => Certificate::typeLabel($this->type),
            'issueDate' => $this->issue_date?->toDateString(),
            'expiryDate' => $this->expiry_date?->toDateString(),
            'status' => $this->status(),
            'daysUntilExpiry' => $this->daysUntilExpiry(),
            'providerName' => $this->provider_name,
            'providerReg' => $this->provider_reg,
            'notes' => $this->notes,
            'file' => $this->file_path ? [
                'name' => $this->file_name,
                'mime' => $this->file_mime,
                'size' => $this->file_size,
                'url' => PrivateFiles::signedUrl('certificate', $this->id),
            ] : null,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
