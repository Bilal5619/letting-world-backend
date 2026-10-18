<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\SuspendsAccounts;
use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantController extends Controller
{
    use SuspendsAccounts;

    public function index(Request $request): View
    {
        $query = User::where('role', User::ROLE_TENANT)
            ->with('landlord', 'assignedProperties')
            ->when(trim((string) $request->query('q')), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])
                    ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)
                    ->orWhereHas('assignedProperties', fn ($p) => $p->where('address_line1', 'like', $like)->orWhere('postcode', 'like', $like)));
            });
        $this->filterByAccountState($query, $request->query('state'));

        return view('admin.tenants.index', [
            'tenants' => $query->latest()->paginate(20)->withQueryString(),
            'total' => User::where('role', User::ROLE_TENANT)->count(),
        ]);
    }

    public function show(User $tenant): View
    {
        abort_unless($tenant->isTenant(), 404);
        $tenant->load('landlord', 'tenancies.property', 'agreements.property');

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'repairs' => MaintenanceRequest::with('property')->where('tenant_id', $tenant->id)->latest()->limit(10)->get(),
            'actions' => AdminAction::with('admin')->where('subject_type', 'user')->where('subject_id', $tenant->id)->latest('created_at')->limit(8)->get(),
        ]);
    }

    public function suspend(Request $request, User $tenant): RedirectResponse
    {
        abort_unless($tenant->isTenant(), 404);

        return $this->suspendAccount($request, $tenant);
    }

    public function reactivate(User $tenant): RedirectResponse
    {
        abort_unless($tenant->isTenant(), 404);

        return $this->reactivateAccount($tenant);
    }
}
