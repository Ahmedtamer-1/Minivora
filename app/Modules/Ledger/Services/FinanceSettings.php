<?php

namespace App\Modules\Ledger\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Modules\Ledger\Models\FinanceSetting;

/** Read and change the current store's finance settings (created with defaults on first read). */
class FinanceSettings
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function current(): FinanceSetting
    {
        return FinanceSetting::query()->firstOrCreate([]);
    }

    /** @param  array{vat_registered: bool, vat_rate_bps: int, gateway_fee_bps: int, gateway_fee_fixed: int, cash_safety_level: int}  $data */
    public function update(array $data): FinanceSetting
    {
        $settings = $this->current();
        $settings->fill($data)->save();
        $this->activity->log('finance.settings_updated', $settings, $data);

        return $settings;
    }
}
