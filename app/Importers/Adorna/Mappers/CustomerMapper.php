<?php

namespace App\Importers\Adorna\Mappers;

use App\Modules\Checkout\Services\EgyptianPhone;
use App\Modules\Orders\Models\Customer;
use Illuminate\Validation\ValidationException;

/**
 * Maps Adorna customers into the current store.
 * Normalizes Egyptian mobile numbers via EgyptianPhone (with graceful fallback for legacy non-mobile numbers)
 * and deduplicates by (store_id, phone).
 */
class CustomerMapper
{
    private const ARABIC_DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /**
     * Normalize an Egyptian mobile number to 01XXXXXXXXX via EgyptianPhone,
     * falling back to a cleaned legacy string for non-mobile numbers (e.g. landlines).
     */
    public static function normalizePhone(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        try {
            return EgyptianPhone::normalize($raw);
        } catch (ValidationException) {
            $clean = strtr(trim($raw), self::ARABIC_DIGITS);
            $clean = preg_replace('/[\s\-()]+/', '', $clean) ?? '';
            $clean = substr($clean, 0, 32);

            return $clean !== '' ? $clean : null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     imported: int,
     *     skipped: int,
     *     by_phone: array<string, Customer>,
     *     by_external_id: array<string, Customer>,
     * }
     */
    public function import(array $rows): array
    {
        $imported = 0;
        $skipped = 0;
        $byPhone = [];
        $byExternalId = [];

        foreach ($rows as $row) {
            $phone = self::normalizePhone(isset($row['phone']) ? (string) $row['phone'] : null);
            $email = isset($row['email']) && trim((string) $row['email']) !== ''
                ? strtolower(trim((string) $row['email']))
                : null;

            $existing = null;
            if ($phone !== null) {
                $existing = Customer::query()->where('phone', $phone)->first();
            } elseif ($email !== null) {
                $existing = Customer::query()->where('email', $email)->first();
            }

            $attributes = [
                'name' => trim((string) ($row['name'] ?? '')),
                'phone' => $phone,
                'email' => $email,
                'governorate' => isset($row['governorate']) ? trim((string) $row['governorate']) : null,
                'city' => isset($row['city']) ? trim((string) $row['city']) : null,
                'address' => isset($row['address']) ? trim((string) $row['address']) : null,
                'tags' => is_array($row['tags'] ?? null) ? array_values($row['tags']) : null,
                'notes' => isset($row['notes']) ? (string) $row['notes'] : null,
            ];

            if ($existing !== null) {
                $existing->fill(array_filter(
                    $attributes,
                    static fn (mixed $value): bool => $value !== null && $value !== '',
                ))->save();
                $customer = $existing;
                $skipped++;
            } else {
                $customer = Customer::query()->create($attributes);
                $imported++;
            }

            if ($phone !== null) {
                $byPhone[$phone] = $customer;
            }
            if (isset($row['id']) || isset($row['external_id'])) {
                $byExternalId[(string) ($row['external_id'] ?? $row['id'])] = $customer;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'by_phone' => $byPhone,
            'by_external_id' => $byExternalId,
        ];
    }
}
