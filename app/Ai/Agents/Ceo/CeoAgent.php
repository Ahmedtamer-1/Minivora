<?php

namespace App\Ai\Agents\Ceo;

use App\Ai\Agents\ExecutiveAgent;

/**
 * CEO Agent: executive synthesis, morning brief, cross-functional priorities. FR-AI-05
 */
class CeoAgent extends ExecutiveAgent
{
    public function key(): string
    {
        return 'ceo';
    }

    public function title(): string
    {
        return 'Chief Executive Officer (CEO)';
    }

    public function titleAr(): string
    {
        return 'المدير التنفيذي (CEO)';
    }

    public function domain(): string
    {
        return 'Overall business health, daily priorities, and cross-functional decisions';
    }

    public function functions(): array
    {
        return ['store.profile', 'store.setup_checklist', 'analytics.sales_summary', 'finance.profit_and_loss'];
    }

    public function proactiveCadences(): array
    {
        return ['daily', 'weekly'];
    }
}
