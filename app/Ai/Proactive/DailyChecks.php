<?php

namespace App\Ai\Proactive;

use App\Ai\Models\Insight;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Orders\Models\Order;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Carbon;

/**
 * Daily proactive checks: detects when day revenue drops below 70% of the 4-week same-weekday
 * average and emits a grounded Insight card. FR-PRO-01, FR-PRO-02, FR-PRO-03
 */
class DailyChecks
{
    public const REVENUE_DROP_THRESHOLD = 0.70;

    public function __construct(
        private readonly CurrentStore $currentStore,
    ) {}

    /**
     * @return list<Insight>
     */
    public function run(Store $store, ?Carbon $asOf = null): array
    {
        $day = ($asOf ?? Carbon::now('Africa/Cairo'))->copy()->setTimezone('Africa/Cairo');

        return $this->currentStore->runAs($store, function () use ($store, $day): array {
            $created = [];

            $todayRevenue = $this->revenueForDay($day);

            $priorRevenues = [];
            for ($weeksBack = 1; $weeksBack <= 4; $weeksBack++) {
                $priorRevenues[] = $this->revenueForDay($day->copy()->subWeeks($weeksBack));
            }

            $fourWeekAvg = array_sum($priorRevenues) / 4;

            if ($fourWeekAvg > 0 && $todayRevenue < ($fourWeekAvg * self::REVENUE_DROP_THRESHOLD)) {
                $ratioPct = round(($todayRevenue / $fourWeekAvg) * 100, 1);
                $dropPiasters = (int) round($fourWeekAvg - $todayRevenue);

                $created[] = Insight::query()->create([
                    'store_id' => $store->id,
                    'agent' => 'cro',
                    'cadence' => 'daily',
                    'type' => 'revenue_drop',
                    'urgency' => 85,
                    'estimated_impact' => $dropPiasters,
                    'title' => sprintf('Revenue is %.1f%% of your 4-week %s average', $ratioPct, $day->format('l')),
                    'summary' => sprintf(
                        'Revenue on %s was %.2f EGP vs. a 4-week %s average of %.2f EGP (%.1f%%).',
                        $day->toDateString(),
                        $todayRevenue / 100,
                        $day->format('l'),
                        $fourWeekAvg / 100,
                        $ratioPct,
                    ),
                    'evidence' => [
                        'date' => $day->toDateString(),
                        'weekday' => $day->format('l'),
                        'today_revenue_piasters' => $todayRevenue,
                        'four_week_weekday_avg_piasters' => (int) round($fourWeekAvg),
                        'ratio_pct' => $ratioPct,
                        'threshold_pct' => (int) (self::REVENUE_DROP_THRESHOLD * 100),
                    ],
                    'recommended_action' => [
                        'label' => 'Inspect traffic, checkout & active campaigns',
                        'href' => '/orders',
                        'function' => null,
                    ],
                    'status' => 'open',
                    'is_urgent' => false,
                ]);
            }

            return $created;
        });
    }

    private function revenueForDay(Carbon $day): int
    {
        $start = $day->copy()->startOfDay()->utc();
        $end = $day->copy()->endOfDay()->utc();

        return (int) Order::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('placed_at', [$start, $end])
            ->sum('total');
    }
}
