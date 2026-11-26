<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves uploaded files (the "public" disk) at /storage/... when the public/storage link is
 * missing. Shared hosts often block PHP from creating that link, which made every uploaded
 * image 404. When the link exists the web server serves the files and this never runs.
 */
class PublicStorageController extends Controller
{
    public function show(string $path): Response
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        abort_if($path === '' || str_contains($path, '..') || str_starts_with($path, '.') || str_contains($path, '/.'), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
