<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgreementResource;
use App\Models\Property;
use App\Models\TenancyAgreement;
use App\Support\PrivateFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Landlord-only: tenancy agreements (contracts). One agreement covers one property and one or
 * more of its tenants. Saving it copies the dates, rent and deposit onto each tenant's tenancy,
 * so tenancy-end alerts and the tenant's screens stay in step automatically.
 */
class AgreementController extends Controller
{
    private const WITH = ['tenants', 'contract', 'property'];

    public function index(Request $request, string $propertyId): AnonymousResourceCollection
    {
        $property = $request->user()->properties()->findOrFail($propertyId);

        return AgreementResource::collection($property->agreements()->with(self::WITH)->orderByDesc('start_date')->get());
    }

    /** Multipart: agreement fields, tenantIds[] and an optional signed contract file. */
    public function store(Request $request, string $propertyId): JsonResponse
    {
        $property = $request->user()->properties()->findOrFail($propertyId);
        $data = $request->validate($this->rules($property) + [
            'contract' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:'.config('lettingworld.upload_max_kb')],
        ], $this->messages(), $this->attributes());

        $agreement = DB::transaction(function () use ($request, $property, $data) {
            $agreement = $property->agreements()->create($this->attributesFrom($data));
            $agreement->tenants()->sync($data['tenantIds']);
            $this->syncTenancies($agreement);
            if ($request->hasFile('contract')) {
                $this->storeContract($request, $agreement);
            }

            return $agreement;
        });

        return (new AgreementResource($agreement->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $id): AgreementResource
    {
        $agreement = $this->find($request, $id);
        $data = $request->validate($this->rules($agreement->property), $this->messages(), $this->attributes());

        DB::transaction(function () use ($agreement, $data) {
            $agreement->update($this->attributesFrom($data));
            $agreement->tenants()->sync($data['tenantIds']);
            $this->syncTenancies($agreement);
        });

        return new AgreementResource($agreement->fresh()->load(self::WITH));
    }

    /** Upload or replace the signed contract (the previous file is archived, not lost). */
    public function uploadContract(Request $request, string $id): AgreementResource
    {
        $agreement = $this->find($request, $id);
        $request->validate([
            'contract' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:'.config('lettingworld.upload_max_kb')],
        ], ['contract.required' => 'Choose the signed contract to upload.', 'contract.max' => 'The file must be 10 MB or smaller.']);

        $this->storeContract($request, $agreement);

        return new AgreementResource($agreement->fresh()->load(self::WITH));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->find($request, $id)->delete(); // contract documents stay on the property (landlord only)

        return response()->json(['ok' => true]);
    }

    private function storeContract(Request $request, TenancyAgreement $agreement): void
    {
        $agreement->loadMissing('tenants');
        $agreement->property->documents()
            ->where('tenancy_agreement_id', $agreement->id)->whereNull('archived_at')
            ->get()->each->update(['archived_at' => now()]);

        $names = $agreement->tenants->map(fn ($t) => trim("{$t->first_name} {$t->last_name}"))->implode(' & ');
        $agreement->property->documents()->create([
            'tenancy_agreement_id' => $agreement->id,
            'category' => 'tenancy_agreement',
            'title' => 'Tenancy agreement'.($names ? " – $names" : ''),
            // Visible to the agreement's tenants through the agreement, not property-wide.
            'shared_with_tenant' => false,
            'uploaded_by' => $request->user()->id,
            ...PrivateFiles::store($request->file('contract'), "documents/{$agreement->property_id}"),
        ]);
    }

    /** Each named tenant's tenancy on this property mirrors the agreement. */
    private function syncTenancies(TenancyAgreement $agreement): void
    {
        $agreement->property->tenancies()
            ->whereIn('tenant_id', $agreement->tenants()->pluck('users.id'))
            ->get()
            ->each->update([
                'start_date' => $agreement->start_date,
                'end_date' => $agreement->end_date,
                'monthly_rent' => $agreement->monthly_rent,
                'rent_due_day' => $agreement->rent_due_day,
                'deposit_amount' => $agreement->deposit_amount,
                'deposit_scheme' => $agreement->deposit_scheme,
            ]);
    }

    private function rules(Property $property): array
    {
        return [
            'tenantIds' => ['required', 'array', 'min:1'],
            // Only tenants already assigned to this property.
            'tenantIds.*' => [Rule::exists('tenancies', 'tenant_id')->where('property_id', $property->id)],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['nullable', 'date_format:Y-m-d', 'after:startDate'],
            'monthlyRent' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'rentDueDay' => ['nullable', 'integer', 'min:1', 'max:28'],
            'depositAmount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'depositScheme' => ['nullable', 'string', 'max:100'],
            'depositReference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function messages(): array
    {
        return [
            'tenantIds.required' => 'Select the tenants named on the agreement.',
            'tenantIds.*.exists' => 'Only tenants assigned to this property can be on the agreement.',
            'endDate.after' => 'End date must be after the start date.',
            'contract.max' => 'The file must be 10 MB or smaller.',
        ];
    }

    private function attributes(): array
    {
        return [
            'tenantIds' => 'tenants', 'startDate' => 'start date', 'endDate' => 'end date',
            'monthlyRent' => 'monthly rent', 'rentDueDay' => 'rent due day', 'depositAmount' => 'deposit',
            'depositScheme' => 'deposit scheme', 'depositReference' => 'deposit reference',
        ];
    }

    private function attributesFrom(array $data): array
    {
        return [
            'start_date' => $data['startDate'],
            'end_date' => $data['endDate'] ?? null,
            'monthly_rent' => $data['monthlyRent'] ?? null,
            'rent_due_day' => $data['rentDueDay'] ?? null,
            'deposit_amount' => $data['depositAmount'] ?? null,
            'deposit_scheme' => $data['depositScheme'] ?? null,
            'deposit_reference' => $data['depositReference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function find(Request $request, string $id): TenancyAgreement
    {
        return TenancyAgreement::whereKey($id)
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $request->user()->id))
            ->with('property')
            ->firstOrFail();
    }
}
