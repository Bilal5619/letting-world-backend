<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\PropertyResource;
use App\Models\Certificate;
use App\Models\MaintenanceRequest;
use App\Models\Tenancy;
use App\Support\Compliance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Landlord home screen: property cards, compliance and "Requires attention" alerts. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $landlord = $request->user();
        $properties = $landlord->properties()->active()
            ->with(['certificates', 'openBookings'])
            ->withCount('tenancies')
            ->latest()
            ->get();

        $totals = ['valid' => 0, 'expiring' => 0, 'expired' => 0, 'missing' => 0, 'renewalBooked' => 0];
        $actions = collect();

        foreach ($properties as $property) {
            foreach (Compliance::summary($property) as $key => $count) {
                $totals[$key] += $count;
            }

            foreach (Compliance::current($property) as $certificate) {
                $status = $certificate->status(); // "renewal_booked" no longer needs attention
                if (in_array($status, [Certificate::STATUS_EXPIRED, Certificate::STATUS_EXPIRING], true)) {
                    $actions->push($this->certificateAction($status, $certificate));
                }
            }
            foreach (Compliance::missing($property) as $type) {
                $actions->push([
                    'kind' => 'missing',
                    'propertyId' => (string) $property->id,
                    'propertyAddress' => $property->shortAddress(),
                    'certificateType' => $type,
                    'certificateLabel' => Certificate::typeLabel($type),
                    'certificateId' => null,
                    'date' => null,
                    'days' => null,
                ]);
            }
        }

        // Tenancy agreements ending within 60 days.
        Tenancy::query()
            ->whereIn('property_id', $properties->pluck('id'))
            ->whereBetween('end_date', [today(), today()->addDays(60)])
            ->with(['tenant', 'property'])
            ->get()
            ->each(fn (Tenancy $t) => $actions->push([
                'kind' => 'tenancy_ending',
                'propertyId' => (string) $t->property_id,
                'propertyAddress' => $t->property->shortAddress(),
                'tenantId' => (string) $t->tenant_id,
                'tenantName' => trim("{$t->tenant->first_name} {$t->tenant->last_name}"),
                'certificateType' => null,
                'certificateLabel' => null,
                'certificateId' => null,
                'date' => $t->end_date->toDateString(),
                'days' => (int) today()->diffInDays($t->end_date, false),
            ]));

        // Repairs waiting on the landlord: newly reported, or urgent/emergency and still open.
        MaintenanceRequest::query()
            ->whereIn('property_id', $properties->pluck('id'))
            ->whereIn('status', MaintenanceRequest::OPEN_STATUSES)
            ->where(fn ($q) => $q->where('status', 'reported')->orWhereIn('priority', ['urgent', 'emergency']))
            ->with('property')
            ->get()
            ->each(fn (MaintenanceRequest $m) => $actions->push([
                'kind' => 'maintenance',
                'propertyId' => (string) $m->property_id,
                'propertyAddress' => $m->property->shortAddress(),
                'maintenanceId' => (string) $m->id,
                'maintenanceTitle' => $m->title,
                'priority' => $m->priority,
                'certificateType' => null,
                'certificateLabel' => null,
                'certificateId' => null,
                'date' => $m->created_at->toDateString(),
                'days' => ['emergency' => -3, 'urgent' => -2, 'normal' => -1, 'low' => 0][$m->priority],
            ]));

        // Most urgent first: expired, repairs, missing, expiring, then tenancies ending.
        $rank = ['expired' => 0, 'maintenance' => 1, 'missing' => 2, 'expiring' => 3, 'tenancy_ending' => 4];
        $actions = $actions->sortBy([
            fn ($a, $b) => $rank[$a['kind']] <=> $rank[$b['kind']],
            fn ($a, $b) => ($a['days'] ?? 0) <=> ($b['days'] ?? 0),
        ])->values();

        $inDate = $totals['valid'] + $totals['expiring'] + $totals['renewalBooked'];
        $tracked = $inDate + $totals['expired'] + $totals['missing'];

        return response()->json([
            'stats' => [
                'properties' => $properties->count(),
                'tenants' => $landlord->tenants()->count(),
                'occupied' => $properties->where('tenancies_count', '>', 0)->count(),
                'monthlyRent' => (float) $properties->sum('monthly_rent'),
                'upcomingBookings' => $landlord->bookings()->whereIn('status', ['requested', 'confirmed'])->count(),
                'openRepairs' => MaintenanceRequest::whereIn('property_id', $properties->pluck('id'))
                    ->whereIn('status', MaintenanceRequest::OPEN_STATUSES)->count(),
            ],
            'compliance' => $totals + ['score' => $tracked ? (int) round($inDate / $tracked * 100) : null],
            'actions' => $actions->take(20),
            'properties' => PropertyResource::collection($properties),
            'upcomingBookings' => BookingResource::collection(
                $landlord->bookings()->with('property')->whereIn('status', ['requested', 'confirmed'])
                    // 11Done's confirmed date first, else the requested one; tenant-scheduled (no date yet) last.
                    ->orderByRaw('COALESCE(scheduled_date, preferred_date) IS NULL, COALESCE(scheduled_date, preferred_date)')
                    ->limit(3)->get()
            ),
            'unreadNotifications' => $landlord->unreadNotifications()->count(),
        ]);
    }

    private function certificateAction(string $status, Certificate $certificate): array
    {
        return [
            'kind' => $status,
            'propertyId' => (string) $certificate->property_id,
            'propertyAddress' => $certificate->property->shortAddress(),
            'certificateType' => $certificate->type,
            'certificateLabel' => Certificate::typeLabel($certificate->type),
            'certificateId' => (string) $certificate->id,
            'date' => $certificate->expiry_date?->toDateString(),
            'days' => $certificate->daysUntilExpiry(),
        ];
    }
}
