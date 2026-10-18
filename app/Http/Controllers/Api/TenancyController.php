<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenancyResource;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Landlord-only: link tenants to properties (many tenants ↔ many properties).
 * Tenancy details (dates, rent, deposit) are optional and can be filled in later.
 */
class TenancyController extends Controller
{
    private const MESSAGES = [
        'propertyId.exists' => 'Select one of your properties.',
        'propertyId.unique' => 'This tenant is already assigned to that property.',
        'endDate.after' => 'End date must be after the start date.',
    ];

    private const ATTRIBUTES = [
        'propertyId' => 'property',
        'tenantIds' => 'tenants',
        'startDate' => 'start date',
        'endDate' => 'end date',
        'monthlyRent' => 'monthly rent',
        'rentDueDay' => 'rent due day',
        'depositAmount' => 'deposit amount',
        'depositScheme' => 'deposit scheme',
    ];

    /** Tenancies for one of the landlord's properties. */
    public function index(Request $request, string $propertyId): AnonymousResourceCollection
    {
        $property = $request->user()->properties()->findOrFail($propertyId);

        return TenancyResource::collection($property->tenancies()->with('tenant', 'property')->get());
    }

    /** "Assign Tenants": links one or more of the landlord's tenants to a property. */
    public function assignMany(Request $request, string $propertyId): JsonResponse
    {
        $landlord = $request->user();
        $property = $landlord->properties()->active()->findOrFail($propertyId);

        $data = $request->validate([
            'tenantIds' => ['required', 'array', 'min:1', 'max:50'],
            'tenantIds.*' => [Rule::exists('users', 'id')->where('role', User::ROLE_TENANT)->where('landlord_id', $landlord->id)],
        ], ['tenantIds.*.exists' => 'Select your own tenants.', 'tenantIds.required' => 'Select at least one tenant.'], self::ATTRIBUTES);

        DB::transaction(function () use ($landlord, $property, $data) {
            $already = $property->tenancies()->pluck('tenant_id')->all();
            foreach (array_unique($data['tenantIds']) as $tenantId) {
                if (! in_array((int) $tenantId, $already, true)) {
                    $property->tenancies()->create(['tenant_id' => $tenantId]);
                }
            }
            self::markLetIfVacant($landlord, $property->id);
        });

        return response()->json(TenancyResource::collection($property->tenancies()->with('tenant', 'property')->get()), 201);
    }

    /** Assigns one tenant to one property (from the tenant's screen). */
    public function store(Request $request, string $tenantId): JsonResponse
    {
        $landlord = $request->user();
        $tenant = $landlord->tenants()->findOrFail($tenantId);

        $data = $request->validate([
            'propertyId' => [
                'required',
                Rule::exists('properties', 'id')->where('landlord_id', $landlord->id)->whereNull('archived_at'),
                Rule::unique('tenancies', 'property_id')->where('tenant_id', $tenant->id),
            ],
            ...self::tenancyRules(),
        ], self::MESSAGES, self::ATTRIBUTES);

        $tenancy = DB::transaction(fn () => self::createTenancy($landlord, $tenant, $data));

        return (new TenancyResource($tenancy->load('property', 'tenant')))->response()->setStatusCode(201);
    }

    /** Edit the tenancy details (dates, rent, deposit). */
    public function update(Request $request, string $id): TenancyResource
    {
        $tenancy = $this->find($request, $id);
        $data = $request->validate(self::tenancyRules(), self::MESSAGES, self::ATTRIBUTES);
        $tenancy->update(self::tenancyAttributes($data));

        return new TenancyResource($tenancy->load('property', 'tenant'));
    }

    /** Removes a tenant from a property. Their account stays (they may rent other properties). */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenancy = $this->find($request, $id);

        DB::transaction(function () use ($tenancy, $request) {
            $tenancy->delete();
            self::markVacantIfEmpty($request->user(), [$tenancy->property_id]);
        });

        return response()->json(['ok' => true]);
    }

    /** @return array<string, list<mixed>> */
    public static function tenancyRules(): array
    {
        return [
            'startDate' => ['nullable', 'date_format:Y-m-d'],
            'endDate' => ['nullable', 'date_format:Y-m-d', 'after:startDate'],
            'monthlyRent' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'rentDueDay' => ['nullable', 'integer', 'min:1', 'max:28'],
            'depositAmount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'depositScheme' => ['nullable', 'string', 'max:100'],
        ];
    }

    public static function tenancyAttributes(array $data): array
    {
        return [
            'start_date' => $data['startDate'] ?? null,
            'end_date' => $data['endDate'] ?? null,
            'monthly_rent' => $data['monthlyRent'] ?? null,
            'rent_due_day' => $data['rentDueDay'] ?? null,
            'deposit_amount' => $data['depositAmount'] ?? null,
            'deposit_scheme' => $data['depositScheme'] ?? null,
        ];
    }

    /** Caller validates; must run inside a transaction. */
    public static function createTenancy(User $landlord, User $tenant, array $data): Tenancy
    {
        if (! $landlord->properties()->active()->whereKey($data['propertyId'])->exists()) {
            throw ValidationException::withMessages(['propertyId' => 'Select one of your properties.']);
        }

        $tenancy = $tenant->tenancies()->create(['property_id' => $data['propertyId']] + self::tenancyAttributes($data));
        self::markLetIfVacant($landlord, (int) $data['propertyId']);

        return $tenancy;
    }

    private static function markLetIfVacant(User $landlord, int $propertyId): void
    {
        $landlord->properties()->whereKey($propertyId)
            ->whereIn('status', ['vacant', 'advertised'])
            ->update(['status' => 'let']);
    }

    /** A "let" property with no tenants left goes back to "vacant". */
    public static function markVacantIfEmpty(User $landlord, array $propertyIds): void
    {
        $landlord->properties()
            ->whereIn('id', $propertyIds)
            ->where('status', 'let')
            ->whereDoesntHave('tenancies')
            ->update(['status' => 'vacant']);
    }

    private function find(Request $request, string $id): Tenancy
    {
        return Tenancy::whereKey($id)
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $request->user()->id))
            ->firstOrFail();
    }
}
