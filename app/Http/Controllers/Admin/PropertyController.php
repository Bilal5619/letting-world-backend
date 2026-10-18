<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Support\Compliance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(Request $request): View
    {
        $archived = $request->query('view') === 'archived';

        $properties = Property::with('landlord', 'certificates', 'openBookings')
            ->withCount('tenants')
            ->when($archived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->when(in_array($request->query('status'), ['let', 'vacant'], true), fn ($q) => $q->where('status', $request->query('status')))
            ->when(trim((string) $request->query('q')), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('address_line1', 'like', $like)->orWhere('city', 'like', $like)
                    ->orWhere('postcode', 'like', $like)->orWhere('reference', 'like', $like)
                    ->orWhereHas('landlord', fn ($l) => $l->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])));
            })
            ->latest()->paginate(20)->withQueryString();

        $properties->getCollection()->each(fn (Property $p) => $p->setAttribute('compliance', Compliance::summary($p)));

        return view('admin.properties.index', [
            'properties' => $properties,
            'archived' => $archived,
            'counts' => ['active' => Property::active()->count(), 'archived' => Property::whereNotNull('archived_at')->count()],
        ]);
    }

    public function show(Property $property): View
    {
        $property->load([
            'landlord.landlordProfile', 'tenancies.tenant', 'certificates', 'openBookings',
            'agreements.tenants', 'agreements.contract',
        ]);
        $current = Compliance::current($property);

        return view('admin.properties.show', [
            'property' => $property,
            'compliance' => Compliance::summary($property),
            'current' => $current->sortBy(fn ($c) => [$c->expiry_date?->timestamp ?? PHP_INT_MAX])->values(),
            'superseded' => $property->certificates->reject(fn ($c) => $current->contains('id', $c->id))->sortByDesc('expiry_date')->values(),
            'missing' => Compliance::missing($property),
            'documents' => $property->documents()->whereNull('archived_at')->latest()->get(),
            'archivedDocuments' => $property->documents()->whereNotNull('archived_at')->count(),
            'bookings' => $property->bookings()->latest()->get(),
            'repairs' => $property->maintenanceRequests()->with('tenant')->latest()->limit(10)->get(),
            'history' => $property->events()->with('user')->latest('created_at')->limit(25)->get(),
        ]);
    }
}
