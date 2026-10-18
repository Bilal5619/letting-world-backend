<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaintenanceResource;
use App\Models\MaintenanceRequest;
use App\Notifications\MaintenanceReported;
use App\Notifications\MaintenanceUpdated;
use App\Support\Maintenance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Tenant-only: report repairs for their own properties and follow progress.
 * A tenant sees the repairs they reported plus ones the landlord logged for the property —
 * never another tenant's reports.
 */
class TenantMaintenanceController extends Controller
{
    public function index(Request $request, string $propertyId): AnonymousResourceCollection
    {
        $this->assertAssigned($request, $propertyId);

        return MaintenanceResource::collection(
            $this->visible($request)->where('property_id', $propertyId)
                ->with(['property', 'reporter', 'photos'])->withCount('updates')->latest()->get()
        );
    }

    public function store(Request $request, string $propertyId): JsonResponse
    {
        $tenant = $request->user();
        $property = $this->assertAssigned($request, $propertyId);
        $data = $request->validate(Maintenance::rules(), Maintenance::messages());

        $m = Maintenance::create($request, $property, $tenant, $data);
        $property->landlord->notify(new MaintenanceReported($m->load('property', 'tenant')));

        return (new MaintenanceResource($m->load(Maintenance::WITH)))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $id): MaintenanceResource
    {
        return new MaintenanceResource($this->visible($request)->whereKey($id)->firstOrFail()->load(Maintenance::WITH));
    }

    /** Tenants can add a note (e.g. "still leaking"); only the landlord changes the status. */
    public function addUpdate(Request $request, string $id): MaintenanceResource
    {
        $m = $this->visible($request)->whereKey($id)->with('property.landlord')->firstOrFail();
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        $update = $m->updates()->create(['user_id' => $request->user()->id, 'note' => trim($data['note'])]);
        $m->property->landlord->notify(new MaintenanceUpdated($m, $update));

        return new MaintenanceResource($m->fresh()->load(Maintenance::WITH));
    }

    private function visible(Request $request): Builder
    {
        $tenant = $request->user();

        return MaintenanceRequest::query()
            ->whereIn('property_id', $tenant->tenancies()->select('property_id'))
            ->where(fn (Builder $q) => $q->where('tenant_id', $tenant->id)
                // Landlord-logged repairs (checked by reporter role, so a deleted tenant's
                // reports never become visible to other tenants).
                ->orWhere(fn (Builder $l) => $l->whereNull('tenant_id')
                    ->whereHas('reporter', fn (Builder $r) => $r->where('role', 'landlord'))));
    }

    private function assertAssigned(Request $request, string $propertyId)
    {
        return $request->user()->assignedProperties()->with('landlord')->findOrFail($propertyId);
    }
}
