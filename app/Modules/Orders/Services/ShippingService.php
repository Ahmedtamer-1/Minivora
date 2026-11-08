<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Models\ShippingZone;

/**
 * Shipping fee per governorate. A store's own zones win; until it sets them up, sensible defaults
 * for Egyptian couriers apply so manual orders still get a fee filled in. FR-ORD-06
 */
class ShippingService
{
    /** The 27 governorates, in the order the address form shows them. */
    public const GOVERNORATES = [
        'cairo', 'giza', 'alexandria', 'qalyubia', 'sharqia', 'dakahlia', 'gharbia', 'monufia', 'beheira',
        'kafr_el_sheikh', 'damietta', 'port_said', 'ismailia', 'suez', 'faiyum', 'beni_suef', 'minya',
        'asyut', 'sohag', 'qena', 'luxor', 'aswan', 'red_sea', 'new_valley', 'matrouh', 'north_sinai', 'south_sinai',
    ];

    /** Default rate in piasters and delivery days when the store has no zone for a governorate. */
    private const DEFAULTS = [
        'cairo' => [6000, 2], 'giza' => [6000, 2],
        'alexandria' => [7000, 3], 'qalyubia' => [7000, 3],
        'red_sea' => [12000, 5], 'new_valley' => [12000, 6], 'matrouh' => [11000, 5],
        'north_sinai' => [12000, 6], 'south_sinai' => [12000, 5],
        'asyut' => [9500, 4], 'sohag' => [9500, 4], 'qena' => [10000, 5], 'luxor' => [10000, 5], 'aswan' => [10500, 5],
    ];

    private const FALLBACK = [8500, 3];

    /**
     * Rate and delivery days for every governorate.
     *
     * @return array<string, array{rate: int, days: int}>
     */
    public function rates(): array
    {
        $zones = ShippingZone::query()->get()->keyBy('governorate');
        $rates = [];
        foreach (self::GOVERNORATES as $gov) {
            $zone = $zones->get($gov);
            [$rate, $days] = self::DEFAULTS[$gov] ?? self::FALLBACK;
            $rates[$gov] = $zone !== null
                ? ['rate' => $zone->rate, 'days' => $zone->delivery_days]
                : ['rate' => $rate, 'days' => $days];
        }

        return $rates;
    }

    public function feeFor(?string $governorate): int
    {
        if ($governorate === null) {
            return 0;
        }

        return $this->rates()[$governorate]['rate'] ?? 0;
    }
}
