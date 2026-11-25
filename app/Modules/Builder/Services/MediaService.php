<?php

namespace App\Modules\Builder\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Builder\Models\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Store media library uploads, bilingual alt text, and bulk deletion.
 * FR-BLD-04, FR-SEO-13
 */
class MediaService
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly ActivityLogger $activity,
    ) {}

    public function store(
        UploadedFile $file,
        ?string $altEn = null,
        ?string $altAr = null,
        string $disk = 'public',
    ): MediaFile {
        $storeId = $this->currentStore->getOrFail()->getKey();
        $path = $file->store("stores/{$storeId}/media", $disk);

        $media = MediaFile::query()->create([
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
            'size_bytes' => (int) ($file->getSize() ?: 0),
            'alt_en' => $altEn !== null ? trim($altEn) : null,
            'alt_ar' => $altAr !== null ? trim($altAr) : null,
        ]);

        $this->activity->log('media.uploaded', $media, ['mime_type' => $media->mime_type]);

        return $media;
    }

    public function updateAltText(MediaFile $media, ?string $altEn, ?string $altAr): MediaFile
    {
        $media->alt_en = $altEn !== null ? trim($altEn) : null;
        $media->alt_ar = $altAr !== null ? trim($altAr) : null;
        $media->save();

        $this->activity->log('media.updated', $media);

        return $media;
    }

    /**
     * Delete multiple media files belonging to the active store from both storage and database.
     *
     * @param  list<int>  $ids
     */
    public function bulkDelete(array $ids): int
    {
        $cleanIds = array_values(array_unique(array_map('intval', $ids)));
        if ($cleanIds === []) {
            return 0;
        }

        $files = MediaFile::query()->whereIn('id', $cleanIds)->get();
        if ($files->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($files): int {
            $byDisk = [];
            foreach ($files as $file) {
                $byDisk[$file->disk][] = $file->path;
            }

            foreach ($byDisk as $disk => $paths) {
                Storage::disk($disk)->delete($paths);
            }

            $deletedIds = $files->pluck('id')->all();
            $count = MediaFile::query()->whereIn('id', $deletedIds)->delete();

            $this->activity->log('media.bulk_deleted', null, ['count' => $count, 'ids' => $deletedIds]);

            return $count;
        });
    }
}
