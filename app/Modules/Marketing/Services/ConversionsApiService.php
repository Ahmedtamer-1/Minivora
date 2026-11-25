<?php

namespace App\Modules\Marketing\Services;

/**
 * Server-side Conversions API payload builder and browser/server deduplication key generator.
 * FR-MKT-04
 */
class ConversionsApiService
{
    public function deduplicationKey(string $eventName, int $orderId): string
    {
        return strtolower(trim($eventName)).':order:'.$orderId;
    }

    /**
     * Build a Meta Conversions API event payload with hashed PII and event_id deduplication.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function buildEventPayload(string $eventName, string $eventId, array $data): array
    {
        $userData = [];

        if (! empty($data['email']) && is_string($data['email'])) {
            $userData['em'] = [hash('sha256', strtolower(trim($data['email'])))];
        }

        if (! empty($data['phone']) && is_string($data['phone'])) {
            $digits = preg_replace('/\D+/', '', $data['phone']) ?? '';
            if ($digits !== '') {
                $userData['ph'] = [hash('sha256', $digits)];
            }
        }

        foreach (['client_ip_address', 'client_user_agent', 'fbc', 'fbp'] as $field) {
            if (! empty($data[$field]) && is_string($data[$field])) {
                $userData[$field] = $data[$field];
            }
        }

        $value = isset($data['value_piasters'])
            ? round(((int) $data['value_piasters']) / 100, 2)
            : (float) ($data['value'] ?? 0);

        $customData = [
            'currency' => strtoupper((string) ($data['currency'] ?? 'EGP')),
            'value' => $value,
        ];

        if (isset($data['order_id'])) {
            $customData['order_id'] = (string) $data['order_id'];
        }

        if (isset($data['content_ids']) && is_array($data['content_ids'])) {
            $customData['content_ids'] = array_values($data['content_ids']);
        }

        if (isset($data['num_items'])) {
            $customData['num_items'] = (int) $data['num_items'];
        }

        return [
            'event_name' => $eventName,
            'event_id' => $eventId,
            'event_time' => (int) ($data['event_time'] ?? time()),
            'action_source' => (string) ($data['action_source'] ?? 'website'),
            'event_source_url' => isset($data['event_source_url']) ? (string) $data['event_source_url'] : null,
            'user_data' => $userData,
            'custom_data' => $customData,
        ];
    }
}
