<?php

namespace App\Modules\Seo\Services;

use App\Modules\Seo\Models\NotFoundLog;
use App\Modules\Seo\Models\Redirect;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Manages automatic and manual URL redirects plus 404 Not Found logging and aggregation.
 * FR-SEO-12, FR-SEO-23, FR-SEO-24
 */
class RedirectService
{
    /** @var list<int> */
    public const ALLOWED_STATUS_CODES = [301, 302, 307, 308];

    /**
     * Create or update an automatic 301 redirect when a slug changes, collapsing redirect chains. FR-SEO-12
     */
    public function recordSlugChange(string $oldPath, string $newPath): Redirect
    {
        $from = $this->normalizePath($oldPath);
        $to = $this->normalizePath($newPath);

        if ($from === $to) {
            throw new InvalidArgumentException('Redirect source and destination paths must differ.');
        }

        // Collapse existing chains that pointed at $from so they jump straight to $to.
        Redirect::query()
            ->where('to_path', $from)
            ->where('from_path', '!=', $to)
            ->update(['to_path' => $to]);

        return Redirect::query()->updateOrCreate(
            ['from_path' => $from],
            [
                'to_path' => $to,
                'status_code' => 301,
                'is_automatic' => true,
            ],
        );
    }

    /**
     * Add or update a manual redirect rule for the current store. FR-SEO-23
     */
    public function addManual(string $fromPath, string $toPath, int $statusCode = 301): Redirect
    {
        $from = $this->normalizePath($fromPath);
        $to = $this->normalizePath($toPath);

        if ($from === $to) {
            throw new InvalidArgumentException('Redirect source and destination paths must differ.');
        }

        if (! in_array($statusCode, self::ALLOWED_STATUS_CODES, true)) {
            throw new InvalidArgumentException("Unsupported redirect status code: {$statusCode}");
        }

        return Redirect::query()->updateOrCreate(
            ['from_path' => $from],
            [
                'to_path' => $to,
                'status_code' => $statusCode,
                'is_automatic' => false,
            ],
        );
    }

    /**
     * Resolve a request path to a store redirect and increment its hit counter. FR-SEO-12, FR-SEO-23
     */
    public function resolve(string $path): ?Redirect
    {
        $normalized = $this->normalizePath($path);

        $redirect = Redirect::query()
            ->where('from_path', $normalized)
            ->first();

        if ($redirect === null) {
            return null;
        }

        $redirect->increment('hits');
        $redirect->refresh();

        return $redirect;
    }

    /**
     * Record a 404 hit for the current store. FR-SEO-24
     */
    public function logNotFound(string $path, ?string $referrer = null): NotFoundLog
    {
        return NotFoundLog::query()->create([
            'path' => $this->normalizePath($path),
            'referrer' => $referrer !== null && trim($referrer) !== '' ? trim($referrer) : null,
            'hit_at' => Carbon::now(),
        ]);
    }

    /**
     * Return the most frequent 404 paths for the current store ordered by hit count descending. FR-SEO-24
     *
     * @return Collection<int, object{path: string, hits: int, last_hit_at: string|null}>
     */
    public function topNotFound(int $limit = 20): Collection
    {
        return NotFoundLog::query()
            ->select([
                'path',
                DB::raw('COUNT(*) as hits'),
                DB::raw('MAX(hit_at) as last_hit_at'),
            ])
            ->groupBy('path')
            ->orderByDesc('hits')
            ->orderBy('path')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn ($row) => (object) [
                'path' => (string) $row->path,
                'hits' => (int) $row->hits,
                'last_hit_at' => $row->last_hit_at !== null ? (string) $row->last_hit_at : null,
            ]);
    }

    public function normalizePath(string $path): string
    {
        $trimmed = trim($path);
        $parsed = parse_url($trimmed, PHP_URL_PATH);
        $clean = is_string($parsed) ? $parsed : $trimmed;
        $clean = '/'.ltrim($clean, '/');

        if ($clean !== '/') {
            $clean = rtrim($clean, '/');
        }

        return $clean;
    }
}
