<?php

namespace App\Ai\Agents\Cxo;

use App\Ai\Agents\ExecutiveAgent;

/**
 * CXO Agent: customer experience, refunds, returns, messaging, and retention. FR-AI-05
 */
class CxoAgent extends ExecutiveAgent
{
    public function key(): string
    {
        return 'cxo';
    }

    public function title(): string
    {
        return 'Chief Experience Officer (CXO)';
    }

    public function titleAr(): string
    {
        return 'مدير تجربة العملاء (CXO)';
    }

    public function domain(): string
    {
        return 'Refund rates, returns, reviews, customer messaging, and retention';
    }

    public function functions(): array
    {
        return ['orders.refund_rate', 'issue_refund', 'message_customers'];
    }

    public function proactiveCadences(): array
    {
        return ['daily', 'weekly'];
    }
}
