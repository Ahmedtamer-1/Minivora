<?php

namespace App\Modules\Builder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Builder\Models\MediaFile;
use App\Modules\Builder\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard media library endpoints: list, upload with AR/EN alt text, update alt text, bulk delete.
 * FR-BLD-04, FR-SEO-13
 */
class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $media,
    ) {}

    public function index(): JsonResponse
    {
        $files = MediaFile::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (MediaFile $file): array => $file->toArray() + ['url' => $file->url()]);

        return response()->json(['files' => $files]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'alt_en' => ['nullable', 'string', 'max:255'],
            'alt_ar' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $this->media->store(
            $request->file('file'),
            $data['alt_en'] ?? null,
            $data['alt_ar'] ?? null,
        );

        return response()->json([
            'file' => $file->toArray() + ['url' => $file->url()],
        ], 201);
    }

    public function update(Request $request, int $media): JsonResponse
    {
        $record = MediaFile::query()->findOrFail($media);

        $data = $request->validate([
            'alt_en' => ['nullable', 'string', 'max:255'],
            'alt_ar' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = $this->media->updateAltText(
            $record,
            array_key_exists('alt_en', $data) ? $data['alt_en'] : $record->alt_en,
            array_key_exists('alt_ar', $data) ? $data['alt_ar'] : $record->alt_ar,
        );

        return response()->json([
            'file' => $updated->toArray() + ['url' => $updated->url()],
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $deleted = $this->media->bulkDelete($data['ids']);

        return response()->json(['deleted' => $deleted]);
    }
}
