<?php

namespace App\Ai\Agents\Risk;

use App\Ai\Agents\ExecutiveAgent;

/**
 * Risk Agent: fraud detection, flagged phones, COD refusal anomalies, audit integrity. FR-AI-05
 */
class RiskAgent extends ExecutiveAgent
{
    public function key(): string
    {
        return 'risk';
    }

    public function title(): string
    {
        return 'Chief Risk Officer (Risk)';
    }

    public function titleAr(): string
    {
        return 'مدير المخاطر (Risk)';
    }

    public function domain(): string
    {
        return 'Fraud prevention, flagged phone numbers, payment failures, and audit anomalies';
    }

    public function functions(): array
    {
        return ['orders.refund_rate'];
    }

    public function proactiveCadences(): array
    {
        return ['hourly', 'daily'];
    }
}
