<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaintenanceResource;
use App\Models\MaintenanceRequest;
use App\Notifications\MaintenanceUpdated;
use App\Support\Maintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Landlord-only: repairs across their properties. */
class MaintenanceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = MaintenanceRequest::query()
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $request->user()->id))
            ->when($request->query('propertyId'), fn ($q, $id) => $q->where('property_id', $id))
            ->when($request->query('status') === 'open', fn ($q) => $q->whereIn('status', MaintenanceRequest::OPEN_STATUSES))
            ->when($request->query('status') === 'closed', fn ($q) => $q->whereNotIn('status', MaintenanceRequest::OPEN_STATUSES))
            ->with(['property', 'reporter', 'photos'])
            ->withCount('updates')
            ->orderByRaw("case priority when 'emergency' then 0 when 'urgent' then 1 when 'normal' then 2 else 3 end")
            ->latest()
            ->get();

        return MaintenanceResource::collection($items);
    }

    public function show(Request $request, string $id): MaintenanceResource
    {
        return new MaintenanceResource($this->find($request, $id)->load(Maintenance::WITH));
    }

    /** The landlord logs an issue themselves (e.g. found at an inspection). */
    public function store(Request $request, string $propertyId): JsonResponse
    {
        $property = $request->user()->properties()->findOrFail($propertyId);
        $data = $request->validate(Maintenance::rules(), Maintenance::messages());

        $m = Maintenance::create($request, $property, $request->user(), $data);

        return (new MaintenanceResource($m->load(Maintenance::WITH)))->response()->setStatusCode(201);
    }

    /** Change status and/or add a note; the tenant who reported it is notified. */
    public function addUpdate(Request $request, string $id): MaintenanceResource
    {
        $m = $this->find($request, $id);
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['reported', 'in_progress', 'scheduled', 'resolved', 'closed'])],
            'note' => ['nullable', 'required_without:status', 'string', 'max:2000'],
        ], ['note.required_without' => 'Add a note or choose a new status.']);

        $status = ($data['status'] ?? null) !== $m->status ? ($data['status'] ?? null) : null;

        $update = DB::transaction(function () use ($m, $data, $status, $request) {
            if ($status) {
                $m->update([
                    'status' => $status,
                    'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? ($m->resolved_at ?? now()) : null,
                ]);
            }

            return $m->updates()->create([
                'user_id' => $request->user()->id,
                'status' => $status,
                'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
            ]);
        });

        if ($m->tenant) {
            $m->tenant->notify(new MaintenanceUpdated($m->load('property'), $update));
        }

        return new MaintenanceResource($m->fresh()->load(Maintenance::WITH));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $m = $this->find($request, $id)->load('photos');
        Maintenance::deleteFiles($m);
        $m->delete();

        return response()->json(['ok' => true]);
    }

    private function find(Request $request, string $id): MaintenanceRequest
    {
        return MaintenanceRequest::whereKey($id)
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $request->user()->id))
            ->firstOrFail();
    }
}
