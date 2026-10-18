<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\User;
use App\Support\Uk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Landlord-only: tenant accounts. The tenant's email is their username; the landlord sets a
 * temporary password which the tenant replaces on first login. Assigning a tenant to
 * properties is handled by TenancyController.
 */
class TenantController extends Controller
{
    private const MESSAGES = [
        'email.unique' => 'An account with this email already exists.',
        'phone.regex' => 'Enter a valid UK mobile number.',
        'propertyId.exists' => 'Select one of your properties.',
        'endDate.after' => 'End date must be after the start date.',
    ];

    private const ATTRIBUTES = [
        'firstName' => 'first name',
        'lastName' => 'last name',
        'phone' => 'mobile number',
        'temporaryPassword' => 'temporary password',
        'propertyId' => 'property',
        'startDate' => 'start date',
        'endDate' => 'end date',
        'monthlyRent' => 'monthly rent',
        'rentDueDay' => 'rent due day',
        'depositAmount' => 'deposit amount',
        'depositScheme' => 'deposit scheme',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenants = $request->user()->tenants()
            ->with('tenancies.property')
            ->orderBy('first_name')->orderBy('last_name')
            ->get();

        return TenantResource::collection($tenants);
    }

    public function show(Request $request, string $id): TenantResource
    {
        return new TenantResource($this->find($request, $id));
    }

    /** Creates the tenant's login, optionally assigning them to a property straight away. */
    public function store(Request $request): JsonResponse
    {
        $landlord = $request->user();
        $this->normalise($request);

        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'regex:'.Uk::PHONE_REGEX],
            'temporaryPassword' => ['required', 'string', 'max:100', Password::min(8)->letters()->numbers()],
            'propertyId' => ['nullable', Rule::exists('properties', 'id')->where('landlord_id', $landlord->id)->whereNull('archived_at')],
            ...TenancyController::tenancyRules(),
        ], self::MESSAGES, self::ATTRIBUTES);

        $tenant = DB::transaction(function () use ($landlord, $data) {
            $tenant = User::create([
                'role' => User::ROLE_TENANT,
                'landlord_id' => $landlord->id,
                'first_name' => trim($data['firstName']),
                'last_name' => trim($data['lastName']),
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['temporaryPassword'],
                'auth_provider' => 'password',
                'must_change_password' => true,
            ]);

            if (! empty($data['propertyId'])) {
                TenancyController::createTenancy($landlord, $tenant, $data);
            }

            return $tenant;
        });

        return (new TenantResource($tenant->load('tenancies.property')))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $id): TenantResource
    {
        $tenant = $this->find($request, $id);
        $this->normalise($request);

        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($tenant->id)],
            'phone' => ['required', 'regex:'.Uk::PHONE_REGEX],
            // Optional: set a new temporary password (e.g. the tenant forgot theirs).
            'temporaryPassword' => ['nullable', 'string', 'max:100', Password::min(8)->letters()->numbers()],
        ], self::MESSAGES, self::ATTRIBUTES);

        $tenant->fill([
            'first_name' => trim($data['firstName']),
            'last_name' => trim($data['lastName']),
            'email' => $data['email'],
            'phone' => $data['phone'],
        ]);
        if (! empty($data['temporaryPassword'])) {
            $tenant->forceFill(['password' => $data['temporaryPassword'], 'must_change_password' => true]);
            $tenant->tokens()->delete(); // sign the tenant out everywhere
        }
        $tenant->save();

        return new TenantResource($tenant->load('tenancies.property'));
    }

    /** Deletes the tenant's account entirely (all their tenancies go with it). */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenant = $this->find($request, $id);
        $propertyIds = $tenant->tenancies->pluck('property_id');

        DB::transaction(function () use ($tenant, $request, $propertyIds) {
            $tenant->tokens()->delete();
            $tenant->tenancies->each->delete(); // model events remove tenancy documents
            $tenant->delete();
            TenancyController::markVacantIfEmpty($request->user(), $propertyIds->all());
        });

        return response()->json(['ok' => true]);
    }

    private function find(Request $request, string $id): User
    {
        return $request->user()->tenants()->with('tenancies.property')->findOrFail($id);
    }

    private function normalise(Request $request): void
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
            'phone' => Uk::normalisePhone($request->input('phone')),
        ]);
    }
}
