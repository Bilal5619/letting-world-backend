<?php

namespace App\Http\Resources;

use App\Models\Document;
use App\Support\PrivateFiles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'propertyId' => (string) $this->property_id,
            'tenancyId' => $this->tenancy_id ? (string) $this->tenancy_id : null,
            'category' => $this->category,
            'categoryLabel' => config("lettingworld.document_categories.{$this->category}", $this->category),
            'title' => $this->title,
            'sharedWithTenant' => $this->shared_with_tenant,
            'tenancyAgreementId' => $this->tenancy_agreement_id ? (string) $this->tenancy_agreement_id : null,
            'archived' => $this->archived_at !== null,
            'file' => [
                'name' => $this->file_name,
                'mime' => $this->file_mime,
                'size' => $this->file_size,
                'url' => PrivateFiles::signedUrl('document', $this->id),
            ],
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
