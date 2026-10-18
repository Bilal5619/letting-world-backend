<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PrivateFiles;
use Illuminate\Http\RedirectResponse;

/** Admins can open any private file: hands out a short-lived signed link. */
class FileController extends Controller
{
    public function __invoke(string $kind, string $id): RedirectResponse
    {
        return redirect()->away(PrivateFiles::signedUrl($kind, (int) $id));
    }
}
