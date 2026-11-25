<?php

namespace App\Ai\Proactive;

use App\Ai\Models\Insight;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Scores and ranks open proactive insights by estimated impact and urgency, enforces the daily
 * feed cap (default 5), and respects Cairo quiet hours while allowing urgent alerts through.
 * FR-PRO-04, FR-PRO-09, NFR-USE-06
 */
class FeedRanker
{
    public const DEFAULT_DAILY_CAP = 5;

    public const QUIET_START_HOUR = 22;

    public const QUIET_END_HOUR = 8;

    public function __construct(
        private readonly CurrentStore $currentStore,
    ) {}

    public function score(Insight $insight): float
    {
        $impactEgp = abs((int) $insight->estimated_impact) / 100;
        $impactScore = log10(1 + $impactEgp) * 25;
        $urgencyScore = ((int) $insight->urgency) * 0.6;
        $urgentBoost = $insight->is_urgent ? 50.0 : 0.0;

        return round($impactScore + $urgencyScore + $urgentBoost, 2);
    }

    public function isQuietHours(?Carbon $now = null, int $quietStartHour = self::QUIET_START_HOUR, int $quietEndHour = self::QUIET_END_HOUR): bool
    {
        $cairo = ($now ?? Carbon::now('Africa/Cairo'))->copy()->setTimezone('Africa/Cairo');
        $hour = $cairo->hour;

        if ($quietStartHour > $quietEndHour) {
            return $hour >= $quietStartHour || $hour < $quietEndHour;
        }

        return $hour >= $quietStartHour && $hour < $quietEndHour;
    }

    /**
     * @return Collection<int, Insight>
     */
    public function rank(
        Store $store,
        int $dailyCap = self::DEFAULT_DAILY_CAP,
        ?Carbon $now = null,
        int $quietStartHour = self::QUIET_START_HOUR,
        int $quietEndHour = self::QUIET_END_HOUR,
    ): Collection {
        $moment = $now ?? Carbon::now('Africa/Cairo');
        $inQuietHours = $this->isQuietHours($moment, $quietStartHour, $quietEndHour);

        return $this->currentStore->runAs($store, function () use ($dailyCap, $moment, $inQuietHours): Collection {
            $query = Insight::query()
                ->where(function ($q) use ($moment) {
                    $q->where('status', 'open')
                        ->orWhere(function ($snoozed) use ($moment) {
                            $snoozed->where('status', 'snoozed')
                                ->whereNotNull('snoozed_until')
                                ->where('snoozed_until', '<=', $moment);
                        });
                });

            if ($inQuietHours) {
                $query->where('is_urgent', true);
            }

            /** @var Collection<int, Insight> $insights */
            $insights = $query->get();

            $ranked = $insights->map(function (Insight $insight): Insight {
                $computed = $this->score($insight);
                if ((float) $insight->score !== $computed) {
                    $insight->forceFill(['score' => $computed])->save();
                }

                return $insight;
            })->sort(function (Insight $a, Insight $b): int {
                if ($a->is_urgent !== $b->is_urgent) {
                    return $b->is_urgent <=> $a->is_urgent;
                }

                return $b->score <=> $a->score;
            })->values();

            return $ranked->take(max(0, $dailyCap));
        });
    }
}
