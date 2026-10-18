<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Certificate;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\PropertyEvent;
use App\Models\User;
use App\Support\Compliance;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Admin home: headline numbers, what needs attention, compliance, payments, latest activity. */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $since = now()->subDays(30);

        $stats = [
            'landlords' => User::where('role', User::ROLE_LANDLORD)->count(),
            'newLandlords' => User::where('role', User::ROLE_LANDLORD)->where('created_at', '>=', $since)->count(),
            'tenants' => User::where('role', User::ROLE_TENANT)->count(),
            'properties' => Property::active()->count(),
            'let' => Property::active()->where('status', 'let')->count(),
            'openBookings' => Booking::whereIn('status', Booking::OPEN_STATUSES)->count(),
            'openRepairs' => MaintenanceRequest::whereIn('status', MaintenanceRequest::OPEN_STATUSES)->count(),
        ];

        return view('admin.dashboard', [
            'stats' => $stats,
            'revenue' => $this->revenue(),
            'compliance' => $this->compliance(),
            'pipeline' => Booking::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'attention' => $this->attention(),
            'upcoming' => Booking::with('property')
                ->whereIn('status', Booking::OPEN_STATUSES)
                ->whereRaw('COALESCE(scheduled_date, preferred_date) BETWEEN ? AND ?', [today()->toDateString(), today()->addDays(7)->toDateString()])
                ->orderByRaw('COALESCE(scheduled_date, preferred_date)')
                ->limit(6)->get(),
            'recentBookings' => Booking::with('property', 'landlord')->latest()->limit(6)->get(),
            'activity' => PropertyEvent::with('property', 'user')->latest('created_at')->limit(8)->get(),
        ]);
    }

    /** Renewal payments collected per month, last 6 months (current month last). */
    private function revenue(): array
    {
        $months = collect(range(5, 0))->map(fn (int $i) => now()->startOfMonth()->subMonths($i));
        $paid = Booking::where('payment_status', 'paid')->where('created_at', '>=', $months->first())->get(['price', 'created_at']);

        $series = $months->map(fn (Carbon $m) => [
            'label' => $m->format('M'),
            'full' => $m->format('F Y'),
            'value' => (float) $paid->filter(fn (Booking $b) => $b->created_at->isSameMonth($m))->sum('price'),
        ])->values();

        $current = $series->last()['value'];
        $previous = $series[$series->count() - 2]['value'];

        return [
            'series' => $series,
            'current' => $current,
            'total' => $series->sum('value'),
            'delta' => $previous > 0 ? round(($current - $previous) / $previous * 100) : null,
        ];
    }

    /** Portfolio-wide: latest certificate of each type per property, plus missing required ones. */
    private function compliance(): array
    {
        $totals = ['valid' => 0, 'renewalBooked' => 0, 'expiring' => 0, 'missing' => 0, 'expired' => 0];
        Property::active()->with('certificates', 'openBookings')->get()
            ->each(function (Property $p) use (&$totals) {
                foreach (Compliance::summary($p) as $key => $count) {
                    $totals[$key] += $count;
                }
            });
        $all = array_sum($totals);

        return [
            'totals' => $totals,
            'all' => $all,
            'score' => $all ? (int) round(($totals['valid'] + $totals['renewalBooked']) / $all * 100) : null,
        ];
    }

    /** @return list<array{icon: string, tone: string, title: string, subtitle: string, url: string}> */
    private function attention(): array
    {
        $items = [];

        foreach (Booking::with('property')->where('sync_status', 'failed')->whereIn('status', Booking::OPEN_STATUSES)->limit(3)->get() as $b) {
            $items[] = ['icon' => 'unplug', 'tone' => 'bad', 'title' => "{$b->reference} couldn’t be sent to 11Done",
                'subtitle' => $b->sync_error ?: 'Retrying automatically', 'url' => route('admin.bookings.show', $b)];
        }
        foreach (Booking::with('property')->where('provider', 'letting_world')->where('status', 'requested')->oldest()->limit(3)->get() as $b) {
            $items[] = ['icon' => 'hard-hat', 'tone' => 'violet', 'title' => 'Arrange a contractor: '.Certificate::typeLabel($b->service_type),
                'subtitle' => $b->property->shortAddress().' · paid '.$b->created_at->diffForHumans(), 'url' => route('admin.bookings.show', $b)];
        }
        foreach (MaintenanceRequest::with('property')->whereIn('status', MaintenanceRequest::OPEN_STATUSES)->whereIn('priority', ['emergency', 'urgent'])
            ->orderByRaw("FIELD(priority, 'emergency', 'urgent')")->limit(2)->get() as $m) {
            $items[] = ['icon' => $m->priority === 'emergency' ? 'siren' : 'triangle-alert', 'tone' => $m->priority === 'emergency' ? 'bad' : 'warn',
                'title' => ucfirst($m->priority).' repair: '.$m->title, 'subtitle' => $m->property->shortAddress().' · reported '.$m->created_at->diffForHumans(),
                'url' => route('admin.maintenance.show', $m)];
        }
        foreach (Certificate::current()->onActiveProperties()->withoutOpenBooking()->with('property')
            ->whereDate('expiry_date', '<', today())->orderBy('expiry_date')->limit(3)->get() as $c) {
            $items[] = ['icon' => 'shield-alert', 'tone' => 'bad', 'title' => Certificate::typeLabel($c->type).' expired',
                'subtitle' => $c->property->shortAddress().' · '.$c->expiry_date->diffForHumans(), 'url' => route('admin.properties.show', $c->property)];
        }

        return $items;
    }
}
