<?php

namespace App\Ai\Proactive;

use App\Ai\Models\Insight;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Orders\Models\Refund;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Carbon;

/**
 * Weekly proactive checks: detects when 7-day refunds exceed 2x the prior 4-week weekly average.
 * FR-PRO-01, FR-PRO-02, FR-PRO-03
 */
class WeeklyChecks
{
    public const REFUND_SPIKE_MULTIPLIER = 2.0;

    public function __construct(
        private readonly CurrentStore $currentStore,
    ) {}

    /**
     * @return list<Insight>
     */
    public function run(Store $store, ?Carbon $asOf = null): array
    {
        $now = ($asOf ?? Carbon::now('Africa/Cairo'))->copy()->setTimezone('Africa/Cairo');

        return $this->currentStore->runAs($store, function () use ($store, $now): array {
            $created = [];

            $currentStart = $now->copy()->subDays(7)->utc();
            $currentEnd = $now->copy()->utc();

            $baselineStart = $now->copy()->subDays(35)->utc();
            $baselineEnd = $currentStart->copy()->subSecond();

            $last7DaysRefunds = (int) Refund::query()
                ->whereBetween('created_at', [$currentStart, $currentEnd])
                ->sum('amount');

            $prior4WeeksTotal = (int) Refund::query()
                ->whereBetween('created_at', [$baselineStart, $baselineEnd])
                ->sum('amount');

            $fourWeekWeeklyAvg = $prior4WeeksTotal / 4;

            if ($fourWeekWeeklyAvg > 0 && $last7DaysRefunds > ($fourWeekWeeklyAvg * self::REFUND_SPIKE_MULTIPLIER)) {
                $multiplier = round($last7DaysRefunds / $fourWeekWeeklyAvg, 2);
                $excessPiasters = (int) round($last7DaysRefunds - $fourWeekWeeklyAvg);

                $created[] = Insight::query()->create([
                    'store_id' => $store->id,
                    'agent' => 'cxo',
                    'cadence' => 'weekly',
                    'type' => 'refund_spike',
                    'urgency' => 88,
                    'estimated_impact' => $excessPiasters,
                    'title' => sprintf('7-day refunds are %.2fx your 4-week weekly average', $multiplier),
                    'summary' => sprintf(
                        'Refunds over the last 7 days totaled %.2f EGP vs. a 4-week weekly average of %.2f EGP (%.2fx).',
                        $last7DaysRefunds / 100,
                        $fourWeekWeeklyAvg / 100,
                        $multiplier,
                    ),
                    'evidence' => [
                        'last_7_days_refunds_piasters' => $last7DaysRefunds,
                        'four_week_weekly_avg_piasters' => (int) round($fourWeekWeeklyAvg),
                        'multiplier' => $multiplier,
                        'threshold_multiplier' => self::REFUND_SPIKE_MULTIPLIER,
                    ],
                    'recommended_action' => [
                        'label' => 'Review recent return reasons & product quality',
                        'href' => '/returns',
                        'function' => null,
                    ],
                    'status' => 'open',
                    'is_urgent' => false,
                ]);
            }

            return $created;
        });
    }
}
