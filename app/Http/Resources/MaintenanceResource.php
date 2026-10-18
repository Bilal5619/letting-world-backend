<?php

namespace App\Http\Resources;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceUpdate;
use App\Models\User;
use App\Support\PrivateFiles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MaintenanceRequest */
class MaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $person = fn (?User $u) => $u ? ['name' => trim("{$u->first_name} {$u->last_name}"), 'role' => $u->role] : null;

        return [
            'id' => (string) $this->id,
            'propertyId' => (string) $this->property_id,
            'propertyAddress' => $this->whenLoaded('property', fn () => $this->property->shortAddress()),
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'categoryLabel' => MaintenanceRequest::categoryLabel($this->category),
            'priority' => $this->priority,
            'status' => $this->status,
            'open' => $this->isOpen(),
            'reportedBy' => $this->whenLoaded('reporter', fn () => $person($this->reporter)),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($p) => [
                'id' => (string) $p->id,
                'url' => PrivateFiles::signedUrl('maintenance-photo', $p->id),
            ])),
            'updates' => $this->whenLoaded('updates', fn () => $this->updates->map(fn (MaintenanceUpdate $u) => [
                'id' => (string) $u->id,
                'by' => $person($u->user),
                'status' => $u->status,
                'note' => $u->note,
                'createdAt' => $u->created_at->toIso8601String(),
            ])),
            'updatesCount' => $this->whenCounted('updates'),
            'createdAt' => $this->created_at->toIso8601String(),
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
