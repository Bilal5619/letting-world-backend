<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Support\PrivateFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Landlord-only: tenancy contracts and other property documents. */
class DocumentController extends Controller
{
    public function index(Request $request, string $propertyId): AnonymousResourceCollection
    {
        $property = $request->user()->properties()->findOrFail($propertyId);

        return DocumentResource::collection(
            $property->documents()
                ->when($request->boolean('archived'), fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
                ->latest()->get()
        );
    }

    public function store(Request $request, string $propertyId): JsonResponse
    {
        $property = $request->user()->properties()->findOrFail($propertyId);

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(config('lettingworld.document_categories')))],
            'title' => ['required', 'string', 'max:255'],
            // Optional: attach to one tenancy on this property (e.g. that tenant's agreement).
            'tenancyId' => ['nullable', Rule::exists('tenancies', 'id')->where('property_id', $property->id)],
            'sharedWithTenant' => ['required', 'boolean'],
            'file' => ['required', 'file', 'mimes:'.config('lettingworld.upload_mimes'), 'max:'.config('lettingworld.upload_max_kb')],
        ], [
            'file.required' => 'Choose a file to upload.',
            'file.mimes' => 'Upload a PDF, photo or Word document.',
            'file.max' => 'The file must be 10 MB or smaller.',
            'tenancyId.exists' => 'Select a tenant on this property.',
        ], ['tenancyId' => 'tenant']);

        $document = $property->documents()->create([
            'category' => $data['category'],
            'title' => trim($data['title']),
            'tenancy_id' => $data['tenancyId'] ?? null,
            'shared_with_tenant' => $data['sharedWithTenant'],
            'uploaded_by' => $request->user()->id,
            ...PrivateFiles::store($request->file('file'), "documents/{$property->id}"),
        ]);

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }

    /** Swap the file for a new version (e.g. a re-signed copy). */
    public function replace(Request $request, string $id): DocumentResource
    {
        $document = $this->find($request, $id);
        $request->validate([
            'file' => ['required', 'file', 'mimes:'.config('lettingworld.upload_mimes'), 'max:'.config('lettingworld.upload_max_kb')],
        ], ['file.required' => 'Choose the new file.', 'file.mimes' => 'Upload a PDF, photo or Word document.', 'file.max' => 'The file must be 10 MB or smaller.']);

        $old = $document->file_path;
        $document->update(PrivateFiles::store($request->file('file'), "documents/{$document->property_id}"));
        PrivateFiles::delete($old);

        return new DocumentResource($document);
    }

    /** Hide from everyone's lists (tenants included) without deleting. */
    public function archive(Request $request, string $id): DocumentResource
    {
        $document = $this->find($request, $id);
        $document->update(['archived_at' => now()]);

        return new DocumentResource($document);
    }

    public function restore(Request $request, string $id): DocumentResource
    {
        $document = $this->find($request, $id);
        $document->update(['archived_at' => null]);

        return new DocumentResource($document);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $document = $this->find($request, $id);

        $document->delete();
        PrivateFiles::delete($document->file_path);

        return response()->json(['ok' => true]);
    }

    private function find(Request $request, string $id): Document
    {
        return Document::whereKey($id)
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $request->user()->id))
            ->firstOrFail();
    }
}
