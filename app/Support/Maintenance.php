<?php

namespace App\Support;

use App\Models\MaintenancePhoto;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Shared create/update logic for landlord and tenant maintenance endpoints. */
class Maintenance
{
    public const WITH = ['property', 'reporter', 'photos', 'updates.user'];

    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'category' => ['required', Rule::in(array_keys(config('lettingworld.maintenance_categories')))],
            'priority' => ['required', Rule::in(['low', 'normal', 'urgent', 'emergency'])],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:'.config('lettingworld.upload_max_kb')],
        ];
    }

    public static function messages(): array
    {
        return [
            'photos.max' => 'Add up to 5 photos.',
            'photos.*.image' => 'Photos must be images.',
            'photos.*.max' => 'Each photo must be 10 MB or smaller.',
        ];
    }

    public static function create(Request $request, Property $property, User $reporter, array $data): MaintenanceRequest
    {
        return DB::transaction(function () use ($request, $property, $reporter, $data) {
            $m = $property->maintenanceRequests()->create([
                'tenant_id' => $reporter->isTenant() ? $reporter->id : null,
                'reported_by' => $reporter->id,
                'title' => trim($data['title']),
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'category' => $data['category'],
                'priority' => $data['priority'],
                'status' => 'reported',
            ]);

            foreach ($request->file('photos', []) as $photo) {
                $stored = PrivateFiles::store($photo, "maintenance/{$property->id}");
                $m->photos()->create([
                    'file_path' => $stored['file_path'],
                    'file_mime' => $stored['file_mime'],
                    'file_size' => $stored['file_size'],
                ]);
            }

            return $m;
        });
    }

    /** Deletes the request's photo files (the rows cascade with the request). */
    public static function deleteFiles(MaintenanceRequest $m): void
    {
        $m->photos->each(fn (MaintenancePhoto $p) => PrivateFiles::delete($p->file_path));
    }
}
