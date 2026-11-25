<?php

use App\Core\Tenancy\CurrentStore;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Models\JournalLine;
use App\Modules\Ledger\Services\ChartOfAccounts as C;
use App\Modules\Ledger\Services\ExpenseService;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Ledger\Services\OrderPosting;
use App\Modules\Ledger\Services\StatementService;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Carbon;

// V-05 Ledger immutability and accounting verification suite. FR-LED-01..04, NFR-SEC-12

test('FR-LED-03 NFR-SEC-12 posted journal entries and lines refuse edits and deletes', function () {
    $store = Store::factory()->create();

    app(CurrentStore::class)->runAs($store, function () {
        $ledger = app(LedgerService::class);
        $entry = $ledger->post('2026-10-05', 'Opening capital', [
            ['code' => C::BANK, 'debit' => 500000],
            ['code' => C::CAPITAL, 'credit' => 500000],
        ]);

        expect($entry)->toBeInstanceOf(JournalEntry::class);

        // Editing memo, amounts, or entry date on a posted JournalEntry must fail.
        expect(function () use ($entry) {
            $e = $entry->fresh();
            $e->memo = 'Tampered memo';
            $e->save();
        })->toThrow(LogicException::class, 'Posted journal entries cannot be edited; reverse them instead.');

        expect(function () use ($entry) {
            $e = $entry->fresh();
            $e->total_debit = 900000;
            $e->total_credit = 900000;
            $e->save();
        })->toThrow(LogicException::class, 'Posted journal entries cannot be edited; reverse them instead.');

        expect(function () use ($entry) {
            $e = $entry->fresh();
            $e->entry_date = '2026-10-10';
            $e->save();
        })->toThrow(LogicException::class, 'Posted journal entries cannot be edited; reverse them instead.');

        // Deleting a posted JournalEntry must fail.
        expect(fn () => $entry->fresh()->delete())
            ->toThrow(LogicException::class, 'Posted journal entries cannot be deleted; reverse them instead.');

        // Editing debit/credit or deleting a posted JournalLine must fail.
        $line = $entry->lines()->firstOrFail();

        expect(function () use ($line) {
            $l = $line->fresh();
            $l->debit = 999999;
            $l->save();
        })->toThrow(LogicException::class, 'Posted journal lines cannot be edited.');

        expect(function () use ($line) {
            $l = $line->fresh();
            $l->credit = 123456;
            $l->save();
        })->toThrow(LogicException::class, 'Posted journal lines cannot be edited.');

        expect(fn () => $line->fresh()->delete())
            ->toThrow(LogicException::class, 'Posted journal lines cannot be deleted.');

        // Original entry and lines remain untouched in the database.
        expect($entry->fresh()->memo)->toBe('Opening capital')
            ->and($entry->fresh()->total_debit)->toBe(500000)
            ->and($entry->fresh()->entry_date->toDateString())->toBe('2026-10-05')
            ->and(JournalLine::query()->where('journal_entry_id', $entry->id)->count())->toBe(2);

        // Reversal via LedgerService::reverse() is the only permitted way to undo an entry.
        $reversal = $ledger->reverse($entry->fresh(), date: '2026-10-06');

        expect($reversal->reversal_of_id)->toBe($entry->id)
            ->and($entry->fresh()->reversed_by_id)->toBe($reversal->id);

        // Once reversed_by_id is set, even modifying reversed_by_id again is refused.
        expect(function () use ($entry) {
            $e = $entry->fresh();
            $e->reversed_by_id = null;
            $e->save();
        })->toThrow(LogicException::class, 'Posted journal entries cannot be edited; reverse them instead.');
    });
});

test('FR-LED-01 FR-LED-02 FR-LED-04 V-05 seeded month trial balance balances and P&L matches hand calculation', function () {
    $store = Store::factory()->create();

    app(CurrentStore::class)->runAs($store, function () use ($store) {
        $ledger = app(LedgerService::class);
        $orders = app(OrderPosting::class);
        $expenses = app(ExpenseService::class);
        $chart = app(C::class);
        $statements = app(StatementService::class);

        // 1. Opening capital (10,000 EGP) and inventory purchase (5,000 EGP).
        $ledger->post('2026-10-01', 'Owner capital injection', [
            ['code' => C::BANK, 'debit' => 1000000],
            ['code' => C::CAPITAL, 'credit' => 1000000],
        ]);
        $ledger->post('2026-10-02', 'Initial inventory purchase', [
            ['code' => C::INVENTORY, 'debit' => 500000],
            ['code' => C::BANK, 'credit' => 500000],
        ]);

        // 2. Card sale with gateway fee (2.75% + 3 EGP) + COGS.
        //    Subtotal 200,000 + shipping 6,000 = 206,000; fee = 5,665 + 300 = 5,965; COGS = 80,000.
        $orders->sync([
            'id' => 101,
            'number' => 101,
            'status' => 'delivered',
            'payment_method' => 'card',
            'subtotal' => 200000,
            'shipping_fee' => 6000,
            'discount' => 0,
            'total' => 206000,
            'cost' => 80000,
            'paid_at' => '2026-10-05T12:00:00+02:00',
            'shipped_at' => '2026-10-06T12:00:00+02:00',
            'closed_at' => null,
        ]);

        // 3. COD delivered sale with discount + COGS.
        //    Subtotal 150,000 + shipping 5,000 - discount 10,000 = 145,000; COGS = 60,000.
        $orders->sync([
            'id' => 102,
            'number' => 102,
            'status' => 'delivered',
            'payment_method' => 'cod',
            'subtotal' => 150000,
            'shipping_fee' => 5000,
            'discount' => 10000,
            'total' => 145000,
            'cost' => 60000,
            'paid_at' => '2026-10-12T12:00:00+02:00',
            'shipped_at' => '2026-10-10T12:00:00+02:00',
            'closed_at' => null,
        ]);

        // 4. Card order paid and then refunded before shipment.
        //    Subtotal 40,000; fee = 1,100 + 300 = 1,400; refund (contra revenue) = 40,000.
        $orders->sync([
            'id' => 103,
            'number' => 103,
            'status' => 'refunded',
            'payment_method' => 'card',
            'subtotal' => 40000,
            'shipping_fee' => 0,
            'discount' => 0,
            'total' => 40000,
            'cost' => 20000,
            'paid_at' => '2026-10-14T12:00:00+02:00',
            'shipped_at' => null,
            'closed_at' => '2026-10-15T12:00:00+02:00',
        ]);

        // 5. Manual fixed rent expense (1,000 EGP paid from bank).
        $expenses->record([
            'expense_date' => '2026-10-20',
            'vendor' => 'Landlord',
            'description' => 'October studio rent',
            'account_id' => $chart->get($store->id, '6100')->id,
            'paid_from' => 'bank',
            'amount' => 100000,
        ]);

        // 6. Variable shipping/courier cost (120 EGP paid from cash).
        $expenses->record([
            'expense_date' => '2026-10-25',
            'vendor' => 'Bosta',
            'description' => 'Courier dispatch fees',
            'account_id' => $chart->get($store->id, C::SHIPPING)->id,
            'paid_from' => 'cash',
            'amount' => 12000,
        ]);

        $from = Carbon::parse('2026-10-01');
        $to = Carbon::parse('2026-10-31');

        // Trial balance: raw sum of all journal lines has 0 difference and StatementService balances.
        $totalDebit = (int) JournalLine::query()->sum('debit');
        $totalCredit = (int) JournalLine::query()->sum('credit');
        $trial = $statements->trialBalance($to);

        expect($totalDebit)->toBeGreaterThan(0)
            ->and($totalDebit)->toBe($totalCredit)
            ->and($trial['balanced'])->toBeTrue()
            ->and($trial['debit'])->toBe($trial['credit']);

        // Hand-calculated P&L in piasters:
        //   Gross revenue:      200,000 + 6,000 + 150,000 + 5,000 + 40,000 = 401,000
        //   Contra revenue:     10,000 (discount) + 40,000 (refund)        =  50,000
        //   Net revenue:        401,000 - 50,000                           = 351,000
        //   COGS:               80,000 + 60,000                            = 140,000
        //   Gross profit:       351,000 - 140,000                          = 211,000
        //   Variable expenses:  5,965 + 1,400 (fees) + 12,000 (shipping)   =  19,365
        //   Contribution:       211,000 - 19,365                           = 191,635
        //   Fixed expenses:     100,000 (rent)                             = 100,000
        //   Net profit:         191,635 - 100,000                          =  91,635
        $pl = $statements->profitAndLoss($from, $to);

        expect($pl['gross_revenue'])->toBe(401000)
            ->and($pl['net_revenue'])->toBe(351000)
            ->and($pl['total_cogs'])->toBe(140000)
            ->and($pl['gross_profit'])->toBe(211000)
            ->and($pl['total_variable'])->toBe(19365)
            ->and($pl['contribution'])->toBe(191635)
            ->and($pl['total_fixed'])->toBe(100000)
            ->and($pl['net_profit'])->toBe(91635);

        // Balance sheet equation: Assets === Liabilities + Equity (including profit to date).
        //   Assets: Cash 133,000 + Bank 400,000 + Clearing 198,635 + Inventory 360,000 = 1,091,635
        //   Liabilities: 0
        //   Equity: Capital 1,000,000 + Profit to date 91,635 = 1,091,635
        $bs = $statements->balanceSheet($to);

        expect($bs['balanced'])->toBeTrue()
            ->and($bs['total_assets'])->toBe(1091635)
            ->and($bs['total_liabilities'])->toBe(0)
            ->and($bs['profit_to_date'])->toBe(91635)
            ->and($bs['total_equity'])->toBe(1091635)
            ->and($bs['total_assets'])->toBe($bs['total_liabilities'] + $bs['total_equity']);
    });
});
