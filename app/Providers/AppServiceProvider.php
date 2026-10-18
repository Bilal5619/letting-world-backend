<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Certificate;
use App\Models\MaintenanceRequest;
use App\Support\History;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // API returns plain objects, not { data: ... }.
        JsonResource::withoutWrapping();

        // Property history is recorded automatically from model events.
        History::register();

        Paginator::defaultView('admin.partials.pagination');

        // Admin sidebar: how many things need someone's attention in each area.
        View::composer('components.admin.layout', function ($view) {
            $view->with('navCounts', [
                'bookings' => Booking::whereIn('status', Booking::OPEN_STATUSES)
                    ->where(fn ($q) => $q->where(fn ($q) => $q->where('provider', 'letting_world')->where('status', 'requested'))
                        ->orWhere('sync_status', 'failed'))
                    ->count(),
                'certificates' => Certificate::current()->onActiveProperties()->withoutOpenBooking()
                    ->whereDate('expiry_date', '<', today())->count(),
                'maintenance' => MaintenanceRequest::whereIn('status', MaintenanceRequest::OPEN_STATUSES)
                    ->whereIn('priority', ['emergency', 'urgent'])->count(),
            ]);
        });
    }
}
