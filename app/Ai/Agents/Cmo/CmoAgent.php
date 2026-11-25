<?php

namespace App\Ai\Agents\Cmo;

use App\Ai\Agents\ExecutiveAgent;

/**
 * CMO Agent: Meta ads verdict, ROAS, attribution, campaign spend approvals. FR-AI-05
 */
class CmoAgent extends ExecutiveAgent
{
    public function key(): string
    {
        return 'cmo';
    }

    public function title(): string
    {
        return 'Chief Marketing Officer (CMO)';
    }

    public function titleAr(): string
    {
        return 'مدير التسويق (CMO)';
    }

    public function domain(): string
    {
        return 'Paid ads, Meta campaign verdicts, ROAS, attribution, and promotions';
    }

    public function functions(): array
    {
        return ['marketing.meta_verdict', 'spend_budget', 'approve_campaign'];
    }

    public function proactiveCadences(): array
    {
        return ['hourly', 'daily', 'weekly'];
    }
}
