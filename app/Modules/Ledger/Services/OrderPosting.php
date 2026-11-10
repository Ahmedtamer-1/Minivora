<?php

namespace App\Modules\Ledger\Services;

use App\Modules\Ledger\Models\JournalEntry;
use Illuminate\Support\Carbon;

/**
 * Brings the books in line with an order's money story. Given an order snapshot, it posts every
 * entry the order should have and has not yet got, each under its own source key, so calling it
 * again (or after every status move, or from the catch-up button) never posts twice.
 *
 *   paid               → sale (and gateway fee for card / wallet)
 *   shipped            → cost of goods sold
 *   cancelled/refunded/returned after being paid  → refund
 *   cancelled/returned after shipping             → stock back at cost
 */
class OrderPosting
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly FinanceSettings $settings,
    ) {}

    /**
     * @param  array{id: int, number: int, status: string, payment_method: string, subtotal: int, shipping_fee: int, discount: int, total: int, cost: int, paid_at: string|null, shipped_at: string|null, closed_at: string|null}  $o
     * @return int entries posted now
     */
    public function sync(array $o): int
    {
        $settings = $this->settings->current();
        $posted = 0;
        $meta = fn (string $kind) => ['source_type' => 'order', 'source_id' => $o['id'], 'source_key' => "order:{$o['id']}:{$kind}", 'auto' => true];
        $post = function (string $kind, ?string $at, string $memoKey, array $lines) use ($o, $meta, &$posted): void {
            if ($at === null || JournalEntry::query()->where('source_key', "order:{$o['id']}:{$kind}")->exists()) {
                return;
            }
            if ($this->ledger->post($this->cairoDate($at), __($memoKey, ['number' => $o['number']]), $lines, $meta($kind)) !== null) {
                $posted++;
            }
        };

        $paid = $o['paid_at'] !== null;
        $online = in_array($o['payment_method'], ['card', 'wallet'], true);
        $closedAfterPaid = $paid && in_array($o['status'], ['cancelled', 'refunded', 'returned'], true);
        $restocked = $o['shipped_at'] !== null && in_array($o['status'], ['cancelled', 'returned'], true);

        $post('sale', $o['paid_at'], 'finance.memo_sale', PostingRules::sale($o['subtotal'], $o['shipping_fee'], $o['discount'], $o['total'], $o['payment_method'], $settings));
        if ($online) {
            $post('fee', $o['paid_at'], 'finance.memo_fee', PostingRules::fee(PostingRules::gatewayFee($o['total'], $settings)));
        }
        if ($o['cost'] > 0) {
            $post('cogs', $o['shipped_at'], 'finance.memo_cogs', PostingRules::costOfGoods($o['cost']));
        }
        if ($closedAfterPaid) {
            $post('refund', $o['closed_at'], 'finance.memo_refund', PostingRules::refund($o['total'], $o['payment_method'], $settings));
        }
        if ($restocked && $o['cost'] > 0) {
            $post('restock', $o['closed_at'], 'finance.memo_restock', PostingRules::restock($o['cost']));
        }

        return $posted;
    }

    /**
     * Has every posting this order needs been made? (month-close check)
     *
     * @param  array<string, true>|null  $keys  all order source keys already posted, to avoid a query per order
     */
    public function isSynced(array $o, ?array $keys = null): bool
    {
        $needed = [];
        if ($o['paid_at'] !== null) {
            $needed[] = 'sale';
        }
        if ($o['shipped_at'] !== null && $o['cost'] > 0) {
            $needed[] = 'cogs';
        }
        if ($o['paid_at'] !== null && in_array($o['status'], ['cancelled', 'refunded', 'returned'], true)) {
            $needed[] = 'refund';
        }
        if ($needed === []) {
            return true;
        }
        if ($keys !== null) {
            return array_filter($needed, fn ($k) => ! isset($keys["order:{$o['id']}:{$k}"])) === [];
        }
        $have = JournalEntry::query()->whereIn('source_key', array_map(fn ($k) => "order:{$o['id']}:{$k}", $needed))->count();

        return $have === count($needed);
    }

    private function cairoDate(string $iso): Carbon
    {
        return Carbon::parse($iso)->setTimezone('Africa/Cairo')->startOfDay();
    }
}
