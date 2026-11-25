<?php

namespace App\Ai\Proactive;

use App\Ai\Models\Approval;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Orders\Models\Order;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Carbon;

/**
 * Compiles the owner's daily Morning Brief: yesterday's sales & orders, today's ranked priorities,
 * and pending approvals count. FR-PRO-06
 */
class MorningBrief
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly FeedRanker $ranker,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function compile(Store $store, ?Carbon $today = null): array
    {
        $day = ($today ?? Carbon::now('Africa/Cairo'))->copy()->setTimezone('Africa/Cairo');
        $yesterday = $day->copy()->subDay();

        return $this->currentStore->runAs($store, function () use ($store, $day, $yesterday): array {
            $start = $yesterday->copy()->startOfDay()->utc();
            $end = $yesterday->copy()->endOfDay()->utc();

            $yesterdayOrders = Order::query()
                ->where('status', '!=', 'cancelled')
                ->whereBetween('placed_at', [$start, $end]);

            $ordersCount = (int) (clone $yesterdayOrders)->count();
            $salesPiasters = (int) (clone $yesterdayOrders)->sum('total');

            // Morning brief always runs as a daytime digest (09:00 Cairo) so non-urgent priorities appear.
            $morningTime = $day->copy()->setTime(9, 0);
            $priorities = $this->ranker->rank($store, FeedRanker::DEFAULT_DAILY_CAP, $morningTime);

            $pendingApprovals = Approval::query()
                ->where('status', 'pending')
                ->count();

            return [
                'date' => $day->toDateString(),
                'yesterday' => [
                    'date' => $yesterday->toDateString(),
                    'orders_count' => $ordersCount,
                    'sales_piasters' => $salesPiasters,
                    'sales_egp' => round($salesPiasters / 100, 2),
                ],
                'priorities' => $priorities->map(fn ($insight) => [
                    'id' => $insight->id,
                    'agent' => $insight->agent,
                    'type' => $insight->type,
                    'title' => $insight->title,
                    'summary' => $insight->summary,
                    'score' => $insight->score,
                    'is_urgent' => $insight->is_urgent,
                    'recommended_action' => $insight->recommended_action,
                ])->values()->all(),
                'pending_approvals_count' => $pendingApprovals,
                'summary' => sprintf(
                    'Yesterday (%s): %d order(s) totaling %.2f EGP. Today: %d priority item(s) and %d pending approval(s).',
                    $yesterday->toDateString(),
                    $ordersCount,
                    $salesPiasters / 100,
                    $priorities->count(),
                    $pendingApprovals,
                ),
            ];
        });
    }
}
