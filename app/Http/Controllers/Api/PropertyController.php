<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\MaintenancePhoto;
use App\Models\Property;
use App\Models\PropertyEvent;
use App\Support\Geocoder;
use App\Support\PrivateFiles;
use App\Support\Uk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Landlord-only. Every query is scoped to the signed-in landlord's own properties. */
class PropertyController extends Controller
{
    /** Needed for the compliance summary (incl. "Renewal booked"). */
    private const WITH = ['certificates', 'openBookings'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $properties = $request->user()->properties()
            ->when(
                $request->boolean('archived'),
                fn ($q) => $q->whereNotNull('archived_at'),
                fn ($q) => $q->whereNull('archived_at'),
            )
            ->withCount('tenancies')
            ->with(self::WITH)
            ->latest()
            ->get();

        return PropertyResource::collection($properties);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $property = $request->user()->properties()->create($this->attributes($data) + $this->location($data['postcode']));

        return (new PropertyResource($property->loadCount('tenancies')->load(self::WITH)))
            ->response()->setStatusCode(201);
    }

    public function show(Request $request, string $id): PropertyResource
    {
        return new PropertyResource($this->find($request, $id));
    }

    public function update(Request $request, string $id): PropertyResource
    {
        $property = $this->find($request, $id);
        $data = $this->validated($request);

        $attributes = $this->attributes($data);
        if ($property->postcode !== $data['postcode'] || $property->latitude === null) {
            $attributes += $this->location($data['postcode']);
        }
        $property->update($attributes);

        return new PropertyResource($property->fresh()->loadCount('tenancies')->load(self::WITH));
    }

    /** Multipart upload of the property photo (replaces any existing one). */
    public function uploadPhoto(Request $request, string $id): PropertyResource
    {
        $property = $this->find($request, $id);
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:'.config('lettingworld.upload_max_kb')],
        ], ['photo.max' => 'The photo must be 10 MB or smaller.', 'photo.image' => 'Choose a photo.']);

        $old = $property->photo_path;
        $file = $request->file('photo');
        $property->update([
            'photo_path' => $file->store("properties/{$property->id}", 'local'),
            'photo_mime' => $file->getMimeType(),
        ]);
        PrivateFiles::delete($old);

        return new PropertyResource($property);
    }

    public function deletePhoto(Request $request, string $id): PropertyResource
    {
        $property = $this->find($request, $id);
        PrivateFiles::delete($property->photo_path);
        $property->update(['photo_path' => null, 'photo_mime' => null]);

        return new PropertyResource($property);
    }

    /** Property history timeline (newest first). */
    public function history(Request $request, string $id): JsonResponse
    {
        $property = $request->user()->properties()->findOrFail($id);

        return response()->json($property->events()->with('user')->latest('created_at')->latest('id')->limit(200)->get()
            ->map(fn (PropertyEvent $e) => [
                'id' => (string) $e->id,
                'type' => $e->type,
                'title' => $e->title,
                'description' => $e->description,
                // Staff show as the company, not by personal name.
                'by' => $e->user ? ($e->user->isAdmin() ? 'Letting World team' : trim("{$e->user->first_name} {$e->user->last_name}")) : null,
                'byRole' => $e->user?->role,
                'meta' => $e->meta,
                'createdAt' => $e->created_at->toIso8601String(),
            ]));
    }

    /** Archive hides a property (e.g. sold) but keeps its history. */
    public function archive(Request $request, string $id): PropertyResource
    {
        $property = $this->find($request, $id);
        if ($property->tenancies_count > 0) {
            throw ValidationException::withMessages([
                'property' => 'Remove the tenants from this property before archiving it.',
            ]);
        }
        $property->update(['archived_at' => now()]);

        return new PropertyResource($property);
    }

    public function restore(Request $request, string $id): PropertyResource
    {
        $property = $this->find($request, $id);
        $property->update(['archived_at' => null]);

        return new PropertyResource($property);
    }

    /** Permanently deletes the property with its certificates, documents and bookings. */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $property = $this->find($request, $id);
        if ($property->tenancies_count > 0) {
            throw ValidationException::withMessages([
                'property' => 'Remove the tenants from this property before deleting it.',
            ]);
        }

        $files = $property->certificates->pluck('file_path')
            ->merge($property->documents()->pluck('file_path'))
            ->merge(MaintenancePhoto::whereIn('maintenance_request_id', $property->maintenanceRequests()->select('id'))->pluck('file_path'))
            ->push($property->photo_path);

        DB::transaction(fn () => $property->delete());
        $files->each(fn ($path) => PrivateFiles::delete($path));

        return response()->json(['ok' => true]);
    }

    private function find(Request $request, string $id): Property
    {
        return $request->user()->properties()->withCount('tenancies')->with(self::WITH)->findOrFail($id);
    }

    private function validated(Request $request): array
    {
        $request->merge(['postcode' => Uk::formatPostcode($request->input('postcode'))]);

        return $request->validate([
            'reference' => ['nullable', 'string', 'max:100'],
            'addressLine1' => ['required', 'string', 'max:255'],
            'addressLine2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'postcode' => ['required', 'regex:'.Uk::POSTCODE_REGEX],
            'propertyType' => ['required', Rule::in(['flat', 'house', 'hmo', 'bungalow', 'maisonette', 'studio', 'other'])],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:50'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:50'],
            'furnishing' => ['required', Rule::in(['furnished', 'unfurnished', 'part_furnished'])],
            'hasGas' => ['required', 'boolean'],
            'monthlyRent' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['let', 'vacant', 'advertised', 'maintenance'])],
        ], ['postcode.regex' => 'Enter a valid UK postcode.'], [
            'reference' => 'property name / reference',
            'addressLine1' => 'address',
            'addressLine2' => 'address line 2',
            'city' => 'town / city',
            'propertyType' => 'property type',
            'monthlyRent' => 'monthly rent',
        ]);
    }

    private function attributes(array $data): array
    {
        return [
            'reference' => filled($data['reference'] ?? null) ? trim($data['reference']) : null,
            'address_line1' => trim($data['addressLine1']),
            'address_line2' => $data['addressLine2'] ?? null,
            'city' => trim($data['city']),
            'postcode' => $data['postcode'],
            'property_type' => $data['propertyType'],
            'bedrooms' => $data['bedrooms'],
            'bathrooms' => $data['bathrooms'],
            'furnishing' => $data['furnishing'],
            'has_gas' => $data['hasGas'],
            'monthly_rent' => $data['monthlyRent'] ?? null,
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            'status' => $data['status'],
        ];
    }

    private function location(string $postcode): array
    {
        [$lat, $lng] = Geocoder::postcode($postcode) ?? [null, null];

        return ['latitude' => $lat, 'longitude' => $lng];
    }
}
