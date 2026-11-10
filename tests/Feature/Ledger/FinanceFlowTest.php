<?php

use App\Core\Permissions\Role;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Catalog\Models\Product;
use App\Modules\Ledger\Models\Expense;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Services\ChartOfAccounts as C;
use App\Modules\Ledger\Services\StatementService;
use App\Modules\Orders\Models\Order;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

// End to end: orders post themselves to the ledger, statements tie out, expenses, month close,
// budgets and the finance pages. FR-LED-01..08, FR-FPA-01..04, UC-10, UC-11

function finStore(Store $store, Closure $fn): mixed
{
    return app(CurrentStore::class)->runAs($store, $fn);
}

/** A manual order for 2 × 850 EGP (cost 300 each), Cairo shipping 60 EGP. */
function finOrder(Store $store, string $method, string $status = 'pending'): Order
{
    $product = finStore($store, fn () => Product::factory()->live()->create(['name' => 'Linen shirt', 'price' => 85000, 'cost' => 30000, 'stock' => 10]));
    test()->post(route('orders.store'), [
        'customer_name' => 'Mona Adel', 'phone' => '01001234567', 'governorate' => 'cairo',
        'channel' => 'instagram', 'payment_method' => $method, 'status' => $status,
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ])->assertSessionHasNoErrors();

    return finStore($store, fn () => Order::query()->latest('id')->firstOrFail());
}

function moveOrder(Order $order, string $status): void
{
    test()->put(route('orders.status', $order->id), ['status' => $status])->assertSessionHasNoErrors();
}

function balanceOf(Store $store, string $code): int
{
    return finStore($store, function () use ($code, $store) {
        $s = app(StatementService::class);
        $id = app(C::class)->get($store->id, $code)->id;

        return $s->balances(null, Carbon::now('Africa/Cairo')->addDay())[$id] ?? 0;
    });
}

function statementsTieOut(Store $store): void
{
    finStore($store, function () {
        $s = app(StatementService::class);
        $day = Carbon::now('Africa/Cairo')->addDay();
        expect($s->trialBalance($day)['balanced'])->toBeTrue()
            ->and($s->balanceSheet($day)['balanced'])->toBeTrue();
    });
}

test('FR-LED-02 a card order posts sale and fee when paid, cost of goods when shipped', function () {
    [, $store] = signedInMember(Role::Owner);
    $order = finOrder($store, 'card', 'paid');                       // total 1,760 EGP

    expect(balanceOf($store, C::CLEARING))->toBe(176000 - (4840 + 300))  // 2.75% + 3 EGP fee
        ->and(balanceOf($store, C::SALES))->toBe(170000)
        ->and(balanceOf($store, C::SHIPPING_INCOME))->toBe(6000)
        ->and(balanceOf($store, C::FEES))->toBe(5140);

    moveOrder($order, 'processing');
    moveOrder($order, 'shipped');
    expect(balanceOf($store, C::COGS))->toBe(60000)
        ->and(balanceOf($store, C::INVENTORY))->toBe(-60000);

    // Moving again never double-posts.
    moveOrder($order, 'delivered');
    expect(finStore($store, fn () => JournalEntry::query()->count()))->toBe(3);

    finStore($store, function () {
        $pl = app(StatementService::class)->profitAndLoss(Carbon::now('Africa/Cairo')->startOfMonth(), Carbon::now('Africa/Cairo'));
        expect($pl['net_revenue'])->toBe(176000)
            ->and($pl['gross_profit'])->toBe(116000)
            ->and($pl['net_profit'])->toBe(116000 - 5140);
    });
    statementsTieOut($store);
});

test('FR-LED-02 cash on delivery is revenue when delivered; a return refunds and restocks', function () {
    [, $store] = signedInMember(Role::Owner);
    $order = finOrder($store, 'cod');
    moveOrder($order, 'processing');
    moveOrder($order, 'shipped');
    expect(balanceOf($store, C::SALES))->toBe(0)->and(balanceOf($store, C::COGS))->toBe(60000);

    moveOrder($order, 'delivered');
    expect(balanceOf($store, C::CASH))->toBe(176000);

    moveOrder($order, 'returned');
    expect(balanceOf($store, C::CASH))->toBe(0)
        ->and(balanceOf($store, C::RETURNS))->toBe(-176000)  // contra revenue, debit balance
        ->and(balanceOf($store, C::COGS))->toBe(0)
        ->and(balanceOf($store, C::INVENTORY))->toBe(0);
    statementsTieOut($store);
});

test('a COD order cancelled before payment posts nothing', function () {
    [, $store] = signedInMember(Role::Owner);
    $order = finOrder($store, 'cod');
    moveOrder($order, 'cancelled');

    expect(finStore($store, fn () => JournalEntry::query()->count()))->toBe(0);
});

test('FR-LED-05 expenses post now or as bills, bills get paid, voiding reverses everything', function () {
    [, $store] = signedInMember(Role::Accountant);
    $rent = finStore($store, fn () => app(C::class)->get($store->id, '6100'));
    $today = Carbon::now('Africa/Cairo')->toDateString();

    $this->post(route('finance.expenses.store'), [
        'expense_date' => $today, 'description' => 'Shop rent', 'vendor' => 'Landlord',
        'account_id' => $rent->id, 'paid_from' => 'bank', 'amount' => '6000',
    ])->assertSessionHasNoErrors();
    expect(balanceOf($store, C::BANK))->toBe(-600000)->and(balanceOf($store, '6100'))->toBe(600000);

    $this->post(route('finance.expenses.store'), [
        'expense_date' => $today, 'description' => 'Boxes', 'account_id' => finStore($store, fn () => app(C::class)->get($store->id, C::PACKAGING)->id),
        'paid_from' => 'payable', 'amount' => '1,140', 'vat' => '140', 'due_date' => $today,
    ])->assertSessionHasNoErrors();
    expect(balanceOf($store, C::PAYABLE))->toBe(114000)->and(balanceOf($store, C::VAT_IN))->toBe(14000);

    $bill = finStore($store, fn () => Expense::query()->where('description', 'Boxes')->firstOrFail());
    $this->post(route('finance.expenses.pay', $bill->id), ['paid_with' => 'cash', 'paid_on' => $today])->assertSessionHasNoErrors();
    expect(balanceOf($store, C::PAYABLE))->toBe(0)->and(balanceOf($store, C::CASH))->toBe(-114000);

    $this->post(route('finance.expenses.void', $bill->id))->assertSessionHasNoErrors();
    expect(balanceOf($store, C::CASH))->toBe(0)->and(balanceOf($store, C::PACKAGING))->toBe(0);
    statementsTieOut($store);
});

test('FR-LED-06 manual entries must balance; transfers move a gateway payout to the bank less charges', function () {
    [, $store] = signedInMember(Role::Owner);
    $id = fn (string $code) => finStore($store, fn () => app(C::class)->get($store->id, $code)->id);
    $today = Carbon::now('Africa/Cairo')->toDateString();

    $this->post(route('finance.journal.store'), ['entry_date' => $today, 'memo' => 'Capital', 'lines' => [
        ['account_id' => $id(C::BANK), 'debit' => '10000'], ['account_id' => $id(C::CAPITAL), 'credit' => '9000'],
    ]])->assertSessionHasErrors('lines');

    $this->post(route('finance.journal.store'), ['entry_date' => $today, 'memo' => 'Capital', 'lines' => [
        ['account_id' => $id(C::CLEARING), 'debit' => '10000'], ['account_id' => $id(C::CAPITAL), 'credit' => '10000'],
    ]])->assertSessionHasNoErrors();

    $this->post(route('finance.transfer'), ['from' => C::CLEARING, 'to' => C::BANK, 'amount' => '10000', 'fee' => '25', 'entry_date' => $today])->assertSessionHasNoErrors();
    expect(balanceOf($store, C::CLEARING))->toBe(0)->and(balanceOf($store, C::BANK))->toBe(997500)->and(balanceOf($store, '6500'))->toBe(2500);

    $entry = finStore($store, fn () => JournalEntry::query()->where('memo', 'Capital')->firstOrFail());
    $this->post(route('finance.journal.reverse', $entry->id))->assertSessionHasNoErrors();
    expect(balanceOf($store, C::CAPITAL))->toBe(0);
    statementsTieOut($store);
});

test('FR-LED-07 last month can be closed after confirming the bank; then it is locked; this month cannot be closed', function () {
    [, $store] = signedInMember(Role::Owner);
    $last = Carbon::now('Africa/Cairo')->subMonthNoOverflow();
    $id = fn (string $code) => finStore($store, fn () => app(C::class)->get($store->id, $code)->id);
    $this->post(route('finance.journal.store'), ['entry_date' => $last->copy()->startOfMonth()->addDays(2)->toDateString(), 'memo' => 'Capital', 'lines' => [
        ['account_id' => $id(C::BANK), 'debit' => '500'], ['account_id' => $id(C::CAPITAL), 'credit' => '500'],
    ]])->assertSessionHasNoErrors();

    $this->post(route('finance.close.store'), ['month' => $last->format('Y-m')])->assertSessionHasErrors('bank_confirmed');
    $this->post(route('finance.close.store'), ['month' => $last->format('Y-m'), 'bank_confirmed' => true])->assertSessionHasNoErrors();
    $this->post(route('finance.close.store'), ['month' => Carbon::now('Africa/Cairo')->format('Y-m'), 'bank_confirmed' => true])->assertSessionHasErrors('month');

    $this->post(route('finance.journal.store'), ['entry_date' => $last->copy()->startOfMonth()->addDays(3)->toDateString(), 'memo' => 'Late', 'lines' => [
        ['account_id' => $id(C::BANK), 'debit' => '5'], ['account_id' => $id(C::CAPITAL), 'credit' => '5'],
    ]])->assertSessionHasErrors('entry_date');

    $this->post(route('finance.close.reopen'), ['month' => $last->format('Y-m')])->assertSessionHasNoErrors();
    $this->get(route('finance.close', ['month' => $last->format('Y-m')]))
        ->assertInertia(fn (Assert $page) => $page->component('finance/close')->where('checklist.closed', false));
});

test('FR-FPA-01 budgets save and compare with actuals', function () {
    [, $store] = signedInMember(Role::Owner);
    finOrder($store, 'instapay', 'paid');
    $sales = finStore($store, fn () => app(C::class)->get($store->id, C::SALES)->id);
    $month = Carbon::now('Africa/Cairo')->format('Y-m');

    $this->put(route('finance.budgets.update'), ['month' => $month, 'amounts' => [$sales => '1000']])->assertSessionHasNoErrors();
    $this->get(route('finance.budgets', ['month' => $month]))->assertInertia(fn (Assert $page) => $page
        ->component('finance/budgets')
        ->where('report.has_budget', true)
        ->where('report.rows', fn ($rows) => collect($rows)->firstWhere('code', C::SALES)['actual'] === 170000
            && collect($rows)->firstWhere('code', C::SALES)['budget'] === 100000
            && collect($rows)->firstWhere('code', C::SALES)['favourable'] === true));
});

test('UC-11 the finance pages render with figures from the ledger', function () {
    [, $store] = signedInMember(Role::Owner);
    finOrder($store, 'card', 'paid');

    $this->get(route('finance.index'))->assertInertia(fn (Assert $page) => $page->component('finance/index')
        ->where('pl.net_revenue', 176000)->where('hasEntries', true)->has('monthly', 12)->has('forecast.months', 3));
    foreach (['pl', 'balance', 'cash', 'trial', 'vat'] as $report) {
        $this->get(route('finance.reports', ['report' => $report, 'period' => 'ytd']))
            ->assertInertia(fn (Assert $page) => $page->component('finance/reports')->where('report', $report));
        $this->get(route('finance.reports.export', ['report' => $report]))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
    $this->get(route('finance.reports', ['report' => 'cash']))->assertInertia(fn (Assert $page) => $page->where('data.closing', 176000 - 5140));
    foreach (['journal', 'accounts', 'settings'] as $tab) {
        $this->get(route('finance.ledger', ['tab' => $tab]))->assertInertia(fn (Assert $page) => $page->component('finance/ledger')->where('tab', $tab));
    }
    $this->get(route('finance.expenses'))->assertInertia(fn (Assert $page) => $page->component('finance/expenses'));
    $this->get(route('finance.close'))->assertInertia(fn (Assert $page) => $page->component('finance/close'));
});

test('finance settings change VAT treatment of new sales', function () {
    [, $store] = signedInMember(Role::Owner);
    $this->put(route('finance.settings.update'), [
        'vat_registered' => true, 'vat_rate' => '14', 'gateway_fee_pct' => '2.75', 'gateway_fee_fixed' => '3', 'cash_safety_level' => '10000',
    ])->assertSessionHasNoErrors();
    finOrder($store, 'cod', 'paid');

    expect(balanceOf($store, C::VAT_OUT))->toBe(21614); // 1,760 × 14 / 114
    statementsTieOut($store);
});

test('NFR-SEC staff cannot see finance; managers can read but not post', function () {
    signedInMember(Role::Staff);
    $this->get(route('finance.index'))->assertForbidden();
    $this->get(route('finance.ledger'))->assertForbidden();

    signedInMember(Role::Manager);
    $this->get(route('finance.reports'))->assertOk();
    $this->post(route('finance.journal.store'), [])->assertForbidden();
    $this->put(route('finance.budgets.update'), [])->assertForbidden();
});
