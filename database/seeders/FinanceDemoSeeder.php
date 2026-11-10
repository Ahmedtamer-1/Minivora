<?php

namespace Database\Seeders;

use App\Core\Tenancy\CurrentStore;
use App\Modules\Catalog\Models\Product;
use App\Modules\Fpa\Services\VarianceService;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Expense;
use App\Modules\Ledger\Services\ChartOfAccounts as C;
use App\Modules\Ledger\Services\ExpenseService;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Ledger\Services\PeriodService;
use App\Modules\Ledger\Services\StatementService;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Six months of believable trading for the "demo" store, so the finance pages have something to
 * show: products with costs, orders on every channel and payment method (some returned or
 * cancelled), monthly running costs, gateway payouts, a budget, and closed months. Every figure is
 * posted through the real services, exactly as the app would.
 */
class FinanceDemoSeeder extends Seeder
{
    private const PRODUCTS = [
        ['Linen shirt', 85000, 32000], ['Leather wallet', 45000, 15000], ['Cotton tote', 25000, 8000],
        ['Silk scarf', 65000, 21000], ['Clay mug', 18000, 5500], ['Wool throw', 120000, 52000],
        ['Brass earrings', 30000, 9000], ['Notebook set', 15000, 4500],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('FinanceDemoSeeder must not run in production.');
        }
        $store = Store::query()->where('subdomain', 'demo')->first();
        if ($store === null) {
            return;
        }

        app(CurrentStore::class)->runAs($store, function () use ($store) {
            if (Expense::query()->exists()) {
                return; // already seeded
            }
            mt_srand(42);
            $realNow = Carbon::now();
            $start = Carbon::now('Africa/Cairo')->startOfMonth()->subMonthsNoOverflow(5);

            $products = array_map(fn ($p) => Product::factory()->live()->create(['name' => $p[0], 'price' => $p[1], 'cost' => $p[2], 'stock' => 400]), self::PRODUCTS);
            $ledger = app(LedgerService::class);
            $orders = app(OrderService::class);
            $expenses = app(ExpenseService::class);
            $chart = app(C::class);
            $id = fn (string $code) => $chart->get($store->id, $code)->id;

            // Opening: the owner puts in capital; stock already on the shelf comes in at cost.
            Carbon::setTestNow($start->copy()->setTime(10, 0));
            $ledger->post($start, "Owner's starting capital", [['code' => C::BANK, 'debit' => 6000000], ['code' => C::CAPITAL, 'credit' => 6000000]], ['source_type' => 'manual']);
            app(PeriodService::class)->syncInventory();

            for ($m = 0; $m < 6; $m++) {
                $month = $start->copy()->addMonthsNoOverflow($m);
                $last = $m === 5 ? $realNow->copy()->setTimezone('Africa/Cairo')->day : $month->daysInMonth;
                $perDay = 1.2 + $m * 0.35;

                for ($day = 1; $day <= $last; $day++) {
                    $n = (int) floor($perDay + mt_rand(0, 100) / 100);
                    for ($k = 0; $k < $n; $k++) {
                        $at = $month->copy()->setDay($day)->setTime(mt_rand(9, 22), mt_rand(0, 59));
                        Carbon::setTestNow($at);
                        $method = ['cod', 'cod', 'cod', 'card', 'card', 'wallet', 'instapay'][mt_rand(0, 6)];
                        $lines = [];
                        foreach ((array) array_rand($products, mt_rand(1, 2)) as $i) {
                            $lines[] = ['product_id' => $products[$i]->id, 'quantity' => mt_rand(1, 2)];
                        }
                        $order = $orders->createManual([
                            'customer_name' => 'Demo shopper '.mt_rand(1, 300), 'phone' => '010'.str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                            'governorate' => ['cairo', 'giza', 'alexandria'][mt_rand(0, 2)], 'channel' => ['website', 'instagram', 'whatsapp'][mt_rand(0, 2)],
                            'payment_method' => $method, 'status' => $method === 'cod' ? 'pending' : 'paid', 'discount' => mt_rand(0, 9) === 0 ? 5000 : 0,
                        ], $lines);

                        $roll = mt_rand(1, 100);
                        // Each later step happens only if its time has already come: nothing is posted in the future.
                        $steps = $roll <= 4
                            ? [[0, OrderStatus::Cancelled]]
                            : [[20, OrderStatus::Processing], [24, OrderStatus::Shipped], [72, OrderStatus::Delivered], ...($roll <= 9 ? [[144, OrderStatus::Returned]] : [])];
                        foreach ($steps as [$hours, $status]) {
                            $when = $at->copy()->addHours($hours);
                            if ($when->gt($realNow)) {
                                break;
                            }
                            Carbon::setTestNow($when);
                            $orders->changeStatus($order, $status);
                        }
                    }
                }

                // Running costs for the month.
                $pay = fn (int $day, string $code, string $what, int $amount, string $from, int $vat = 0, ?string $vendor = null) => $day <= $last
                    ? $expenses->record(['expense_date' => $month->copy()->setDay($day)->toDateString(), 'description' => $what, 'vendor' => $vendor, 'account_id' => $id($code), 'paid_from' => $from, 'amount' => $amount, 'vat' => $vat, 'due_date' => $from === 'payable' ? $month->copy()->setDay(min($month->daysInMonth, $day + 14))->toDateString() : null])
                    : null;
                Carbon::setTestNow($month->copy()->setDay(1)->setTime(9, 0));
                $pay(1, '6100', 'Studio rent', 600000, 'bank', 0, 'Landlord');
                $pay(3, '6400', 'Software subscriptions', 45000, 'card', 0, 'SaaS');
                $pay(5, C::PACKAGING, 'Boxes and tissue', 114000 + $m * 20000, 'payable', 14000 + $m * 2456, 'Cairo Packaging');
                $pay(8, C::MARKETING, 'Instagram ads', 250000 + $m * 60000, 'card', 0, 'Meta');
                $pay(10, '6300', 'Internet and electricity', 52000, 'cash');
                $pay(25, '6200', 'Packer salary', 700000, 'bank');
                $pay(27, C::SHIPPING, 'Courier invoice', 180000 + $m * 45000, 'payable', 0, 'Bosta');

                // Gateway payout to the bank around the 20th, less charges.
                if ($last >= 20) {
                    Carbon::setTestNow($month->copy()->setDay(20)->setTime(12, 0));
                    $clearing = app(StatementService::class)->balances(null, $month->copy()->setDay(20))[$id(C::CLEARING)] ?? 0;
                    if ($clearing > 10000) {
                        $ledger->post($month->copy()->setDay(20), 'Transfer: payment gateway to bank', [
                            ['code' => C::BANK, 'debit' => $clearing - 2500], ['code' => '6500', 'debit' => 2500], ['code' => C::CLEARING, 'credit' => $clearing],
                        ], ['source_type' => 'manual']);
                    }
                }

                // Pay last month's bills.
                foreach (Expense::query()->whereNull('paid_at')->where('paid_from', 'payable')->where('due_date', '<', $month->copy()->setDay(min($last, 12))->toDateString())->get() as $bill) {
                    if ($month->copy()->setDay(min($last, 12))->gt($realNow)) {
                        break;
                    }
                    Carbon::setTestNow($month->copy()->setDay(min($last, 12)));
                    $expenses->markPaid($bill, 'bank', $month->copy()->setDay(min($last, 12))->toDateString());
                }
            }

            // A budget for this month and last, from last month's actuals plus a little ambition.
            Carbon::setTestNow($realNow);
            $variance = app(VarianceService::class);
            $now = Carbon::now('Africa/Cairo');
            $variance->copyFromPrevious($now->copy()->subMonthNoOverflow());
            $variance->copyFromPrevious($now);
            $sales = Account::query()->where('code', C::SALES)->value('id');
            $variance->save($now, [$sales => (int) round(($variance->report($now->copy()->subMonthNoOverflow())['rows'][0]['actual'] ?? 0) * 1.15)]);

            // Close every finished month except the last one, so there is something left to close.
            $periods = app(PeriodService::class);
            for ($m = 0; $m < 4; $m++) {
                $periods->close($start->copy()->addMonthsNoOverflow($m), true);
            }
        });
        Carbon::setTestNow();
    }
}
