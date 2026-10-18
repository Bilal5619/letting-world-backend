<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Support\PrivateFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Landlord-only: certificate records and uploaded certificate files. */
class CertificateController extends Controller
{
    /** All certificates across the landlord's properties (or one property), soonest expiry first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $certificates = Certificate::query()
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $request->user()->id)->whereNull('archived_at'))
            ->when($request->query('propertyId'), fn ($q, $id) => $q->where('property_id', $id))
            ->with('property')
            ->orderByRaw('expiry_date is null, expiry_date asc')
            ->get();

        return CertificateResource::collection($certificates);
    }

    /** Multipart upload: file is optional so a landlord can record dates before scanning the paper copy. */
    public function store(Request $request, string $propertyId): JsonResponse
    {
        $property = $request->user()->properties()->findOrFail($propertyId);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('lettingworld.certificate_types')))],
            'issueDate' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'expiryDate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issueDate'],
            'providerName' => ['nullable', 'string', 'max:255'],
            'providerReg' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:'.config('lettingworld.upload_mimes'), 'max:'.config('lettingworld.upload_max_kb')],
        ], [
            'file.mimes' => 'Upload a PDF, photo or Word document.',
            'file.max' => 'The file must be 10 MB or smaller.',
            'issueDate.before_or_equal' => 'The issue date cannot be in the future.',
            'expiryDate.after_or_equal' => 'The expiry date must be after the issue date.',
        ], [
            'issueDate' => 'issue date',
            'expiryDate' => 'expiry date',
            'providerName' => 'engineer / company',
            'providerReg' => 'registration number',
        ]);

        // Work out the expiry from the issue date when only that was entered.
        $expiry = $data['expiryDate'] ?? null;
        $months = config("lettingworld.certificate_types.{$data['type']}.months");
        if (! $expiry && ! empty($data['issueDate']) && $months) {
            $expiry = Carbon::parse($data['issueDate'])->addMonthsNoOverflow($months)->toDateString();
        }

        $file = $request->hasFile('file')
            ? PrivateFiles::store($request->file('file'), "certificates/{$property->id}")
            : [];

        $certificate = $property->certificates()->create([
            'type' => $data['type'],
            'issue_date' => $data['issueDate'] ?? null,
            'expiry_date' => $expiry,
            'provider_name' => $data['providerName'] ?? null,
            'provider_reg' => $data['providerReg'] ?? null,
            'notes' => $data['notes'] ?? null,
            'uploaded_by' => $request->user()->id,
            ...$file,
        ]);

        return (new CertificateResource($certificate->load('property')))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $certificate = Certificate::whereKey($id)
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $request->user()->id))
            ->firstOrFail();

        $certificate->delete();
        PrivateFiles::delete($certificate->file_path);

        return response()->json(['ok' => true]);
    }
}
