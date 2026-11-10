<?php

namespace App\Modules\Ledger\Enums;

/**
 * The five account types of double-entry bookkeeping. Assets and expenses grow with debits;
 * liabilities, equity and revenue grow with credits ("normal balance"). FR-LED-01
 */
enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function debitNormal(): bool
    {
        return $this === self::Asset || $this === self::Expense;
    }

    /** Shows on the P&L (temporary accounts, closed into retained earnings each year). */
    public function isTemporary(): bool
    {
        return $this === self::Revenue || $this === self::Expense;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
