<?php

namespace App\Ai\Agents\Cro;

use App\Ai\Agents\ExecutiveAgent;

/**
 * CRO Agent: revenue growth, top products, checkout conversion, abandoned carts. FR-AI-05
 */
class CroAgent extends ExecutiveAgent
{
    public function key(): string
    {
        return 'cro';
    }

    public function title(): string
    {
        return 'Chief Revenue Officer (CRO)';
    }

    public function titleAr(): string
    {
        return 'مدير الإيرادات (CRO)';
    }

    public function domain(): string
    {
        return 'Sales velocity, top products, pricing, and checkout recovery';
    }

    public function functions(): array
    {
        return ['analytics.sales_summary', 'analytics.top_products', 'checkout.abandoned_checkouts', 'change_price'];
    }

    public function proactiveCadences(): array
    {
        return ['daily', 'weekly'];
    }
}
