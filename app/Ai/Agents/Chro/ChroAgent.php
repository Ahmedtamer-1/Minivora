<?php

namespace App\Ai\Agents\Chro;

use App\Ai\Agents\ExecutiveAgent;

/**
 * CHRO Agent: store team roles, permissions hygiene, and operational workload. FR-AI-05
 */
class ChroAgent extends ExecutiveAgent
{
    public function key(): string
    {
        return 'chro';
    }

    public function title(): string
    {
        return 'Chief Human Resources Officer (CHRO)';
    }

    public function titleAr(): string
    {
        return 'مدير الموارد البشرية (CHRO)';
    }

    public function domain(): string
    {
        return 'Team roles, staff activity, approval throughput, and shift coverage';
    }

    public function functions(): array
    {
        return ['store.setup_checklist'];
    }

    public function proactiveCadences(): array
    {
        return ['weekly'];
    }
}
