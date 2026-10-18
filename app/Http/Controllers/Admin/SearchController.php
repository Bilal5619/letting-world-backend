<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Certificate;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** ⌘K search across people, properties and bookings. */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $like = '%'.addcslashes($q, '%_\\').'%';

        $people = User::whereIn('role', [User::ROLE_LANDLORD, User::ROLE_TENANT])
            ->where(fn ($w) => $w->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])
                ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
            ->limit(6)->get()
            ->map(fn (User $u) => [
                'group' => $u->isLandlord() ? 'Landlords' : 'Tenants',
                'title' => $u->fullName(),
                'subtitle' => $u->email,
                'icon' => $u->isLandlord() ? 'user-round' : 'users',
                'url' => $u->isLandlord() ? route('admin.landlords.show', $u) : route('admin.tenants.show', $u),
            ])->sortBy('group');

        $properties = Property::with('landlord')
            ->where(fn ($w) => $w->where('address_line1', 'like', $like)->orWhere('postcode', 'like', $like)
                ->orWhere('city', 'like', $like)->orWhere('reference', 'like', $like))
            ->limit(5)->get()
            ->map(fn (Property $p) => [
                'group' => 'Properties', 'title' => $p->shortAddress(),
                'subtitle' => $p->postcode.' · '.$p->landlord?->fullName(), 'icon' => 'building-2',
                'url' => route('admin.properties.show', $p),
            ]);

        $bookings = Booking::with('property')
            ->where(fn ($w) => $w->where('reference', 'like', $like)->orWhere('provider_reference', 'like', $like))
            ->limit(5)->get()
            ->map(fn (Booking $b) => [
                'group' => 'Bookings', 'title' => $b->reference.' · '.Certificate::typeLabel($b->service_type),
                'subtitle' => $b->property->shortAddress().($b->provider_reference ? ' · 11Done '.$b->provider_reference : ''),
                'icon' => 'calendar-check', 'url' => route('admin.bookings.show', $b),
            ]);

        return response()->json($people->concat($properties)->concat($bookings)->values());
    }
}
