<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Repairs reported across all properties (landlords manage them in the app; admins oversee). */
class MaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $view = in_array($request->query('view'), ['open', 'resolved', 'all'], true) ? $request->query('view') : 'open';
        $priority = in_array($request->query('priority'), ['emergency', 'urgent', 'normal', 'low'], true) ? $request->query('priority') : null;

        $base = MaintenanceRequest::query()
            ->when($priority, fn ($q) => $q->where('priority', $priority))
            ->when(trim((string) $request->query('q')), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)
                    ->orWhereHas('property', fn ($p) => $p->where('address_line1', 'like', $like)->orWhere('postcode', 'like', $like)));
            });
        $scope = fn ($q, string $v) => match ($v) {
            'open' => $q->whereIn('status', MaintenanceRequest::OPEN_STATUSES),
            'resolved' => $q->whereNotIn('status', MaintenanceRequest::OPEN_STATUSES),
            default => $q,
        };

        return view('admin.maintenance.index', [
            'view' => $view,
            'priority' => $priority,
            'counts' => collect(['open', 'resolved', 'all'])->mapWithKeys(fn ($v) => [$v => $scope(clone $base, $v)->count()]),
            'requests' => $scope($base, $view)->with('property.landlord', 'tenant', 'reporter')->withCount('photos')
                ->orderByRaw("FIELD(priority, 'emergency', 'urgent', 'normal', 'low')")->latest()
                ->paginate(20)->withQueryString(),
        ]);
    }

    public function show(MaintenanceRequest $maintenance): View
    {
        return view('admin.maintenance.show', [
            'request' => $maintenance->load('property.landlord', 'tenant', 'reporter', 'photos', 'updates.user'),
        ]);
    }
}
