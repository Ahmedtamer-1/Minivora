<?php

namespace App\Ai\Agents\Coo;

use App\Ai\Agents\ExecutiveAgent;

/**
 * COO Agent: daily orders, stock alerts, fulfillment, and shipping operations. FR-AI-05
 */
class CooAgent extends ExecutiveAgent
{
    public function key(): string
    {
        return 'coo';
    }

    public function title(): string
    {
        return 'Chief Operating Officer (COO)';
    }

    public function titleAr(): string
    {
        return 'مدير العمليات (COO)';
    }

    public function domain(): string
    {
        return 'Inventory levels, reorder alerts, order fulfillment, and delivery SLAs';
    }

    public function functions(): array
    {
        return ['catalog.low_stock', 'orders.today_summary'];
    }

    public function proactiveCadences(): array
    {
        return ['hourly', 'daily'];
    }
}
