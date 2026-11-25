<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Models\ShippingZone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

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

        return $this->rateForGovernorate($governorate) ?? 0;
    }

    /** @return Collection<int, ShippingZone> */
    public function listZones(): Collection
    {
        return ShippingZone::query()
            ->orderBy('sort_order')
            ->orderBy('governorate')
            ->get();
    }

    public function upsertZone(string $governorate, int $rate, int $deliveryDays, int $sortOrder = 0): ShippingZone
    {
        return ShippingZone::query()->updateOrCreate(
            ['governorate' => trim($governorate)],
            [
                'rate' => max(0, $rate),
                'delivery_days' => max(1, $deliveryDays),
                'sort_order' => max(0, $sortOrder),
            ],
        );
    }

    public function deleteZone(ShippingZone $zone): void
    {
        $zone->delete();
    }

    public function rateForGovernorate(string $governorate): ?int
    {
        $zone = ShippingZone::query()->where('governorate', $governorate)->first();
        if ($zone !== null) {
            return $zone->rate;
        }

        if (! in_array($governorate, self::GOVERNORATES, true)) {
            return null;
        }

        [$rate] = self::DEFAULTS[$governorate] ?? self::FALLBACK;

        return $rate;
    }

    /**
     * Estimated delivery date window for a governorate. FR-CAT-07, FR-ORD-06
     *
     * @return array{min_date: string, max_date: string, delivery_days: int}|null
     */
    public function deliveryEstimate(string $governorate, ?Carbon $from = null): ?array
    {
        $zone = ShippingZone::query()->where('governorate', $governorate)->first();
        if ($zone !== null) {
            $deliveryDays = $zone->delivery_days;
        } elseif (in_array($governorate, self::GOVERNORATES, true)) {
            [, $deliveryDays] = self::DEFAULTS[$governorate] ?? self::FALLBACK;
        } else {
            return null;
        }

        $base = ($from?->copy() ?? Carbon::now('Africa/Cairo'))->startOfDay();

        return [
            'min_date' => $base->copy()->addDays($deliveryDays)->toDateString(),
            'max_date' => $base->copy()->addDays($deliveryDays + 1)->toDateString(),
            'delivery_days' => $deliveryDays,
        ];
    }
}
