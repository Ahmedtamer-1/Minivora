<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Marketing\Models\Subscriber;
use Illuminate\Support\Carbon;

/**
 * Newsletter subscription capture and UTF-8 BOM CSV export. FR-MKT-05
 */
class NewsletterService
{
    public const UTF8_BOM = "\xEF\xBB\xBF";

    public function subscribe(string $email, ?string $name = null, string $source = 'storefront'): Subscriber
    {
        $normalizedEmail = strtolower(trim($email));
        $trimmedName = $name !== null && trim($name) !== '' ? trim($name) : null;

        return Subscriber::query()->updateOrCreate(
            ['email' => $normalizedEmail],
            [
                'name' => $trimmedName,
                'source' => substr(trim($source) !== '' ? trim($source) : 'storefront', 0, 40),
                'subscribed_at' => Carbon::now(),
            ],
        );
    }

    public function exportCsv(): string
    {
        $subscribers = Subscriber::query()
            ->orderBy('subscribed_at')
            ->orderBy('id')
            ->get(['email', 'name', 'source', 'subscribed_at']);

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['email', 'name', 'source', 'subscribed_at']);

        foreach ($subscribers as $subscriber) {
            fputcsv($stream, [
                $subscriber->email,
                $subscriber->name ?? '',
                $subscriber->source,
                $subscriber->subscribed_at->toIso8601String(),
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return self::UTF8_BOM.$csv;
    }
}
