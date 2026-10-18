<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgreementResource;
use App\Http\Resources\CertificateResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\TenancyResource;
use App\Models\Document;
use App\Models\Tenancy;
use App\Support\Compliance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant-only. Everything starts from the signed-in tenant's own tenancies, so a tenant can
 * never reach another property — changing an ID in the request simply returns 404.
 */
class TenantPropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenancies = $request->user()->tenancies()->with('property')->get();

        return response()->json($tenancies->map(fn (Tenancy $t) => [
            'property' => new PropertyResource($t->property),
            'tenancy' => new TenancyResource($t),
        ]));
    }

    public function show(Request $request, string $propertyId): JsonResponse
    {
        $tenancy = $request->user()->tenancies()
            ->where('property_id', $propertyId)
            ->with('property.landlord', 'property.certificates', 'property.openBookings')
            ->firstOrFail();

        $property = $tenancy->property;
        $landlord = $property->landlord;

        return response()->json([
            'property' => new PropertyResource($property),
            'tenancy' => new TenancyResource($tenancy),
            'landlord' => [
                'name' => trim("{$landlord->first_name} {$landlord->last_name}"),
                'email' => $landlord->email,
                'phone' => $landlord->phone,
            ],
            // Current certificate of each type (e.g. the Gas Safety record tenants are entitled to).
            'certificates' => CertificateResource::collection(Compliance::current($property)->values()),
            // Only agreements this tenant is named on.
            'agreements' => AgreementResource::collection(
                $request->user()->agreements()->where('property_id', $property->id)
                    ->with('tenants', 'contract')->orderByDesc('start_date')->get()
            ),
            'documents' => DocumentResource::collection(
                Document::visibleToTenancy($tenancy)->latest()->get()
            ),
        ]);
    }
}
