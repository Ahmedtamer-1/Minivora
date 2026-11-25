<?php

namespace App\Ai\Agents\Cto;

use App\Ai\Agents\ExecutiveAgent;

/**
 * CTO Agent: storefront reliability, 404 spikes, payment gateway health, page publishing. FR-AI-05
 */
class CtoAgent extends ExecutiveAgent
{
    public function key(): string
    {
        return 'cto';
    }

    public function title(): string
    {
        return 'Chief Technology Officer (CTO)';
    }

    public function titleAr(): string
    {
        return 'مدير التكنولوجيا (CTO)';
    }

    public function domain(): string
    {
        return 'Storefront uptime, payment gateway errors, 404 spikes, and page publishing';
    }

    public function functions(): array
    {
        return ['store.profile', 'publish_page'];
    }

    public function proactiveCadences(): array
    {
        return ['hourly'];
    }
}
