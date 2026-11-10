<?php

use App\Core\Tenancy\CurrentStore;
use App\Modules\Fpa\Services\ForecastService;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Models\Period;
use App\Modules\Ledger\Services\ChartOfAccounts as C;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Platform\Models\Store;
use Illuminate\Validation\ValidationException;

// FR-LED-03 unbalanced entries are rejected; FR-LED-07 closed months are locked.

function inLedgerStore(Closure $fn): mixed
{
    $store = Store::factory()->create();

    return app(CurrentStore::class)->runAs($store, $fn);
}

test('FR-LED-03 an unbalanced entry is refused and nothing is written', function () {
    inLedgerStore(function () {
        expect(fn () => app(LedgerService::class)->post('2026-10-01', 'Bad', [
            ['code' => C::CASH, 'debit' => 1000],
            ['code' => C::CAPITAL, 'credit' => 900],
        ]))->toThrow(ValidationException::class);
        expect(JournalEntry::query()->count())->toBe(0);
    });
});

test('FR-LED-03 one-line, two-sided and negative lines are refused', function () {
    inLedgerStore(function () {
        $ledger = app(LedgerService::class);
        expect(fn () => $ledger->post('2026-10-01', 'One line', [['code' => C::CASH, 'debit' => 1000]]))->toThrow(ValidationException::class)
            ->and(fn () => $ledger->post('2026-10-01', 'Both', [['code' => C::CASH, 'debit' => 10, 'credit' => 10], ['code' => C::CAPITAL, 'credit' => 0]]))->toThrow(ValidationException::class)
            ->and(fn () => $ledger->post('2026-10-01', 'Negative', [['code' => C::CASH, 'debit' => -10], ['code' => C::CAPITAL, 'credit' => -10]]))->toThrow(ValidationException::class);
    });
});

test('FR-LED-03 the model refuses an unbalanced header even outside the service', function () {
    inLedgerStore(function () {
        JournalEntry::query()->create(['number' => 1, 'entry_date' => '2026-10-01', 'total_debit' => 5, 'total_credit' => 4]);
    });
})->throws(LogicException::class);

test('a balanced entry is posted once per source key and reverses cleanly', function () {
    inLedgerStore(function () {
        $ledger = app(LedgerService::class);
        $lines = [['code' => C::CASH, 'debit' => 50000], ['code' => C::CAPITAL, 'credit' => 50000]];
        $a = $ledger->post('2026-10-01', 'Capital', $lines, ['source_key' => 'test:1']);
        $b = $ledger->post('2026-10-01', 'Capital', $lines, ['source_key' => 'test:1']);

        expect($a->id)->toBe($b->id)->and($a->number)->toBe(1)->and($a->isBalanced())->toBeTrue();

        $r = $ledger->reverse($a);
        expect($r->number)->toBe(2)->and($r->reversal_of_id)->toBe($a->id)
            ->and($a->fresh()->reversed_by_id)->toBe($r->id)
            ->and(fn () => $ledger->reverse($a->fresh()))->toThrow(ValidationException::class);
    });
});

test('FR-LED-07 a closed month refuses manual entries; automatic ones move to an open day', function () {
    inLedgerStore(function () {
        Period::query()->create(['month' => '2026-09-01', 'closed_at' => now()]);
        $ledger = app(LedgerService::class);
        $lines = [['code' => C::CASH, 'debit' => 100], ['code' => C::CAPITAL, 'credit' => 100]];

        expect(fn () => $ledger->post('2026-09-15', 'Late', $lines))->toThrow(ValidationException::class);
        $auto = $ledger->post('2026-09-15', 'Late refund', $lines, ['auto' => true]);
        expect($auto->entry_date->format('Y-m'))->not->toBe('2026-09');
    });
});

test('exponential smoothing and break-even follow the documented formulas', function () {
    expect(ForecastService::smooth([]))->toBe(0)
        ->and(ForecastService::smooth([100, 100, 100]))->toBe(100)
        ->and(ForecastService::smooth([0, 100]))->toBe(50)         // 0.5·100 + 0.5·0
        ->and(ForecastService::breakEven(30000, 40000, 100000))->toBe(50000) // 30000 / (1 − 0.4)
        ->and(ForecastService::breakEven(30000, 100000, 100000))->toBeNull()
        ->and(ForecastService::breakEven(30000, 0, 0))->toBeNull();
});
