<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Models\PageView;
use App\Modules\Analytics\Models\VisitSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * First-party storefront page-view and visit-session tracking with strict consent gating.
 * FR-ANA-01, FR-ANA-06
 */
class TrackingService
{
    /**
     * Record a page view (and its visit session if a session_key is provided).
     * Returns null and writes nothing when consent has not been granted.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordPageView(array $data, bool $consentGranted): ?PageView
    {
        if (! $consentGranted) {
            return null;
        }

        $viewedAt = isset($data['viewed_at']) ? Carbon::parse($data['viewed_at']) : Carbon::now();
        $deviceType = isset($data['device_type']) && is_string($data['device_type']) && trim($data['device_type']) !== ''
            ? substr(trim($data['device_type']), 0, 20)
            : 'mobile';

        $visitSessionId = null;
        if (isset($data['session_key']) && is_string($data['session_key']) && trim($data['session_key']) !== '') {
            $sessionKey = substr(trim($data['session_key']), 0, 100);
            $session = VisitSession::query()->firstOrCreate(
                ['session_key' => $sessionKey],
                [
                    'utm_source' => $this->nullableString($data['utm_source'] ?? null, 80),
                    'utm_medium' => $this->nullableString($data['utm_medium'] ?? null, 80),
                    'utm_campaign' => $this->nullableString($data['utm_campaign'] ?? null, 120),
                    'referrer' => $this->nullableString($data['referrer'] ?? null, 500),
                    'device_type' => $deviceType,
                    'started_at' => $viewedAt,
                ],
            );
            $visitSessionId = $session->id;
        }

        return PageView::query()->create([
            'visit_session_id' => $visitSessionId,
            'path' => substr((string) ($data['path'] ?? '/'), 0, 500),
            'product_id' => isset($data['product_id']) ? (int) $data['product_id'] : null,
            'referrer' => $this->nullableString($data['referrer'] ?? null, 500),
            'utm_source' => $this->nullableString($data['utm_source'] ?? null, 80),
            'utm_medium' => $this->nullableString($data['utm_medium'] ?? null, 80),
            'utm_campaign' => $this->nullableString($data['utm_campaign'] ?? null, 120),
            'device_type' => $deviceType,
            'viewed_at' => $viewedAt,
        ]);
    }

    /** @return Collection<int, VisitSession> */
    public function recentSessions(int $limit = 50): Collection
    {
        return VisitSession::query()
            ->latest('started_at')
            ->limit(max(1, min(200, $limit)))
            ->get();
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : substr($trimmed, 0, $maxLength);
    }
}
