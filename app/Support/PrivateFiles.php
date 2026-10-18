<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Certificates and documents live on the private "local" disk (storage/app/private) and are
 * only reachable through short-lived signed links handed to users who passed authorisation.
 */
class PrivateFiles
{
    /** @return array{file_path: string, file_name: string, file_mime: string, file_size: int} */
    public static function store(UploadedFile $file, string $folder): array
    {
        return [
            'file_path' => $file->store($folder, 'local'),
            'file_name' => mb_substr($file->getClientOriginalName() ?: 'file', 0, 255),
            'file_mime' => $file->getMimeType() ?? 'application/octet-stream',
            'file_size' => (int) $file->getSize(),
        ];
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    /** @param 'certificate'|'document'|'property'|'maintenance-photo' $kind */
    public static function signedUrl(string $kind, int $id): string
    {
        return URL::temporarySignedRoute(
            'files.show',
            now()->addMinutes(config('lettingworld.file_link_minutes')),
            ['kind' => $kind, 'id' => $id],
        );
    }
}
