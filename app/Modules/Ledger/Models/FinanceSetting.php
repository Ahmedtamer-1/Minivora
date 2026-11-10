<?php

namespace App\Modules\Ledger\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-store finance settings: VAT registration and rate, payment gateway fee, cash safety level.
 *
 * @property int $id
 * @property int $store_id
 * @property bool $vat_registered
 * @property int $vat_rate_bps
 * @property int $gateway_fee_bps
 * @property int $gateway_fee_fixed
 * @property int $cash_safety_level
 */
class FinanceSetting extends Model
{
    use BelongsToStore;

    protected $fillable = ['vat_registered', 'vat_rate_bps', 'gateway_fee_bps', 'gateway_fee_fixed', 'cash_safety_level'];

    protected $attributes = [
        'vat_registered' => false,
        'vat_rate_bps' => 1400,
        'gateway_fee_bps' => 275,
        'gateway_fee_fixed' => 300,
        'cash_safety_level' => 1000000,
    ];

    protected function casts(): array
    {
        return [
            'vat_registered' => 'boolean',
            'vat_rate_bps' => 'integer',
            'gateway_fee_bps' => 'integer',
            'gateway_fee_fixed' => 'integer',
            'cash_safety_level' => 'integer',
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'vat_registered' => $this->vat_registered,
            'vat_rate_bps' => $this->vat_rate_bps,
            'gateway_fee_bps' => $this->gateway_fee_bps,
            'gateway_fee_fixed' => $this->gateway_fee_fixed,
            'cash_safety_level' => $this->cash_safety_level,
        ];
    }
}
