<?php

namespace App\Ai\Agents\Cfo;

use App\Ai\Agents\ExecutiveAgent;

/**
 * CFO Agent: profit & loss, cash balance, budget variance, break-even. FR-AI-05
 */
class CfoAgent extends ExecutiveAgent
{
    public function key(): string
    {
        return 'cfo';
    }

    public function title(): string
    {
        return 'Chief Financial Officer (CFO)';
    }

    public function titleAr(): string
    {
        return 'المدير المالي (CFO)';
    }

    public function domain(): string
    {
        return 'Profit and loss, cash flow, budget variance, margins, and break-even';
    }

    public function functions(): array
    {
        return ['finance.profit_and_loss', 'finance.cash_balance', 'fpa.budget_variance'];
    }

    public function proactiveCadences(): array
    {
        return ['daily', 'weekly'];
    }
}
