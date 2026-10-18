<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A tenant account as their landlord sees it. @mixin \App\Models\User */
class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'hasLoggedIn' => $this->last_login_at !== null,
            'mustChangePassword' => (bool) $this->must_change_password,
            // Controllers eager-load tenancies.property.
            'tenancies' => TenancyResource::collection($this->whenLoaded('tenancies')),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
