<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Property;
use App\Support\Compliance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Compliance across every property: the latest certificate of each type, filterable by
 * status, plus required certificates that were never uploaded ("Missing").
 */
class CertificateController extends Controller
{
    private const TABS = ['expired', 'expiring', 'renewal_booked', 'valid', 'no_expiry', 'missing', 'all'];

    public function __invoke(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'expired';
        $type = array_key_exists((string) $request->query('type'), config('lettingworld.certificate_types')) ? $request->query('type') : null;

        $base = Certificate::current()->onActiveProperties()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when(trim((string) $request->query('q')), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->whereHas('property', fn ($p) => $p->where('address_line1', 'like', $like)->orWhere('postcode', 'like', $like)
                    ->orWhereHas('landlord', fn ($l) => $l->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])));
            });

        $missing = $this->missing($type);
        $counts = collect(self::TABS)->mapWithKeys(fn ($t) => [
            $t => $t === 'missing' ? $missing->sum(fn ($row) => count($row['types'])) : $this->tab(clone $base, $t)->count(),
        ]);

        return view('admin.certificates.index', [
            'tab' => $tab,
            'type' => $type,
            'counts' => $counts,
            'certificates' => $tab === 'missing' ? null : $this->tab($base, $tab)
                ->with('property.landlord', 'property.openBookings')
                ->orderByRaw('expiry_date IS NULL, expiry_date')
                ->paginate(25)->withQueryString(),
            'missing' => $missing,
        ]);
    }

    private function tab(Builder $query, string $tab): Builder
    {
        $today = today()->toDateString();
        $soon = today()->addDays((int) config('lettingworld.expiring_soon_days'))->toDateString();

        return match ($tab) {
            'expired' => $query->withoutOpenBooking()->whereDate('expiry_date', '<', $today),
            'expiring' => $query->withoutOpenBooking()->whereDate('expiry_date', '>=', $today)->whereDate('expiry_date', '<=', $soon),
            'renewal_booked' => $query->withOpenBooking()->whereDate('expiry_date', '<=', $soon),
            'valid' => $query->whereDate('expiry_date', '>', $soon),
            'no_expiry' => $query->whereNull('expiry_date'),
            default => $query,
        };
    }

    /** Active properties missing a required certificate type. */
    private function missing(?string $type)
    {
        return Property::active()->with('certificates', 'landlord')->get()
            ->map(fn (Property $p) => ['property' => $p, 'types' => array_values(array_filter(
                Compliance::missing($p), fn ($t) => ! $type || $t === $type,
            ))])
            ->filter(fn ($row) => $row['types'])
            ->values();
    }
}
