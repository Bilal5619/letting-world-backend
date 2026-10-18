<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Document;
use App\Models\MaintenancePhoto;
use App\Models\Property;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves private files. Only reachable through a signed, expiring URL (the `signed`
 * middleware) — those URLs are only ever generated for users already authorised to see the file.
 */
class FileController extends Controller
{
    public function __invoke(string $kind, string $id): StreamedResponse
    {
        if ($kind === 'property') {
            $property = Property::findOrFail($id);
            [$path, $name, $mime] = [$property->photo_path, 'property-photo', $property->photo_mime];
        } elseif ($kind === 'maintenance-photo') {
            $photo = MaintenancePhoto::findOrFail($id);
            [$path, $name, $mime] = [$photo->file_path, 'repair-photo', $photo->file_mime];
        } else {
            $model = match ($kind) {
                'certificate' => Certificate::findOrFail($id),
                'document' => Document::findOrFail($id),
                default => abort(404),
            };
            [$path, $name, $mime] = [$model->file_path, $model->file_name, $model->file_mime];
        }

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $name, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
