<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\SuspendsAccounts;
use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use App\Support\Compliance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandlordController extends Controller
{
    use SuspendsAccounts;

    public function index(Request $request): View
    {
        $query = User::where('role', User::ROLE_LANDLORD)
            ->with('landlordProfile')
            ->withCount([
                'properties' => fn ($q) => $q->whereNull('archived_at'),
                'tenants',
                'bookings',
            ])
            ->withSum(['bookings as paid_total' => fn ($q) => $q->where('payment_status', 'paid')], 'price')
            ->when(trim((string) $request->query('q')), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])
                    ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)
                    ->orWhereHas('landlordProfile', fn ($p) => $p->where('company_name', 'like', $like)));
            });
        $this->filterByAccountState($query, $request->query('state'));

        return view('admin.landlords.index', [
            'landlords' => $query->latest()->paginate(20)->withQueryString(),
            'total' => User::where('role', User::ROLE_LANDLORD)->count(),
        ]);
    }

    public function show(User $landlord): View
    {
        abort_unless($landlord->isLandlord(), 404);
        $landlord->load('landlordProfile');

        $properties = $landlord->properties()->with('certificates', 'openBookings')->withCount('tenants')
            ->orderByRaw('archived_at IS NOT NULL')->latest()->get()
            ->each(fn (Property $p) => $p->setAttribute('compliance', Compliance::summary($p)));

        return view('admin.landlords.show', [
            'landlord' => $landlord,
            'properties' => $properties,
            'tenants' => $landlord->tenants()->with('assignedProperties')->orderBy('first_name')->get(),
            'bookings' => $landlord->bookings()->with('property')->latest()->limit(8)->get(),
            'paid' => (float) $landlord->bookings()->where('payment_status', 'paid')->sum('price'),
            'openBookings' => $landlord->bookings()->whereIn('status', Booking::OPEN_STATUSES)->count(),
            'actions' => AdminAction::with('admin')->where('subject_type', 'user')->where('subject_id', $landlord->id)->latest('created_at')->limit(8)->get(),
        ]);
    }

    public function suspend(Request $request, User $landlord): RedirectResponse
    {
        abort_unless($landlord->isLandlord(), 404);

        return $this->suspendAccount($request, $landlord);
    }

    public function reactivate(User $landlord): RedirectResponse
    {
        abort_unless($landlord->isLandlord(), 404);

        return $this->reactivateAccount($landlord);
    }
}
