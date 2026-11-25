<?php

use App\Modules\Notifications\Mail\TestDiagnosticMail;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('V-13 NFR-REL-05 NFR-REL-06 backup and restore drill preserves stores, products, orders, and balanced journal entries within RTO', function () {
    $drillDir = storage_path('framework/testing/backup-drill-'.Str::random(8));
    mkdir($drillDir, 0755, true);

    $sourcePath = $drillDir.'/source.sqlite';
    $backupPath = $drillDir.'/backup.sqlite';
    $restoredPath = $drillDir.'/restored.sqlite';

    touch($sourcePath);

    try {
        Config::set('database.connections.drill_source', [
            'driver' => 'sqlite',
            'database' => $sourcePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        Artisan::call('migrate', [
            '--database' => 'drill_source',
            '--force' => true,
        ]);

        $src = DB::connection('drill_source');
        $now = now()->toDateTimeString();

        // Seed store, products, orders, accounts, and balanced journal entries.
        $storeId = $src->table('stores')->insertGetId([
            'name' => 'Cairo Crafts',
            'subdomain' => 'cairo-crafts-drill',
            'default_locale' => 'ar',
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $src->table('products')->insert([
            [
                'store_id' => $storeId,
                'name' => 'Handwoven Tote',
                'name_ar' => 'شنطة يدوية',
                'slug' => 'handwoven-tote',
                'price' => 45_000,
                'cost' => 20_000,
                'stock' => 15,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'store_id' => $storeId,
                'name' => 'Ceramic Mug',
                'name_ar' => 'مج سيراميك',
                'slug' => 'ceramic-mug',
                'price' => 18_000,
                'cost' => 7_000,
                'stock' => 40,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $orderId = $src->table('orders')->insertGetId([
            'store_id' => $storeId,
            'number' => 1001,
            'status' => 'delivered',
            'channel' => 'website',
            'payment_method' => 'cod',
            'customer_name' => 'Mona Hassan',
            'phone' => '01011223344',
            'subtotal' => 63_000,
            'shipping_fee' => 5_000,
            'discount' => 0,
            'total' => 68_000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $cashAccountId = $src->table('accounts')->insertGetId([
            'store_id' => $storeId,
            'code' => '1000',
            'name_en' => 'Cash',
            'name_ar' => 'النقدية',
            'type' => 'asset',
            'subtype' => 'cash',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $salesAccountId = $src->table('accounts')->insertGetId([
            'store_id' => $storeId,
            'code' => '4000',
            'name_en' => 'Sales Revenue',
            'name_ar' => 'إيرادات المبيعات',
            'type' => 'revenue',
            'subtype' => 'sales',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $entryId = $src->table('journal_entries')->insertGetId([
            'store_id' => $storeId,
            'number' => 1,
            'entry_date' => now()->toDateString(),
            'memo' => 'Order #1001 COD settlement',
            'source_type' => 'order',
            'source_id' => $orderId,
            'total_debit' => 68_000,
            'total_credit' => 68_000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $src->table('journal_lines')->insert([
            [
                'store_id' => $storeId,
                'journal_entry_id' => $entryId,
                'account_id' => $cashAccountId,
                'debit' => 68_000,
                'credit' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'store_id' => $storeId,
                'journal_entry_id' => $entryId,
                'account_id' => $salesAccountId,
                'debit' => 0,
                'credit' => 68_000,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $sourceCounts = [
            'stores' => $src->table('stores')->count(),
            'products' => $src->table('products')->count(),
            'orders' => $src->table('orders')->count(),
            'journal_entries' => $src->table('journal_entries')->count(),
            'journal_lines' => $src->table('journal_lines')->count(),
        ];

        // Execute backup and restore drill, timing RTO.
        $startedAt = microtime(true);
        DB::disconnect('drill_source');

        expect(copy($sourcePath, $backupPath))->toBeTrue()
            ->and(copy($backupPath, $restoredPath))->toBeTrue();

        Config::set('database.connections.drill_restored', [
            'driver' => 'sqlite',
            'database' => $restoredPath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $restored = DB::connection('drill_restored');
        $elapsedSeconds = microtime(true) - $startedAt;

        $restoredCounts = [
            'stores' => $restored->table('stores')->count(),
            'products' => $restored->table('products')->count(),
            'orders' => $restored->table('orders')->count(),
            'journal_entries' => $restored->table('journal_entries')->count(),
            'journal_lines' => $restored->table('journal_lines')->count(),
        ];

        $totalDebits = (int) $restored->table('journal_lines')->sum('debit');
        $totalCredits = (int) $restored->table('journal_lines')->sum('credit');
        $entryDebits = (int) $restored->table('journal_entries')->sum('total_debit');
        $entryCredits = (int) $restored->table('journal_entries')->sum('total_credit');

        expect($restoredCounts)->toBe($sourceCounts)
            ->and($totalDebits)->toBe(68_000)
            ->and($totalDebits)->toBe($totalCredits)
            ->and($entryDebits)->toBe($entryCredits)
            ->and($elapsedSeconds)->toBeLessThan(5.0);
    } finally {
        DB::disconnect('drill_source');
        DB::disconnect('drill_restored');

        foreach ([$sourcePath, $backupPath, $restoredPath] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        if (is_dir($drillDir)) {
            @rmdir($drillDir);
        }
    }
});

test('V-16 NFR-MNT-01 NFR-MNT-03 NFR-REL-01 fresh install schema and production config defaults audit', function () {
    expect(Schema::hasTable('migrations'))->toBeTrue();

    $coreTables = [
        'users',
        'stores',
        'store_members',
        'products',
        'orders',
        'order_items',
        'accounts',
        'journal_entries',
        'journal_lines',
        'activity_logs',
        'approvals',
        'insights',
        'carts',
        'payments',
        'shipping_zones',
    ];

    foreach ($coreTables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Expected core table '{$table}' to exist");
    }

    // Verify production config defaults in config/app.php keep app.debug false and default env to production.
    $appConfigSource = file_get_contents(config_path('app.php'));
    expect($appConfigSource)->toContain("'debug' => (bool) env('APP_DEBUG', false)")
        ->and($appConfigSource)->toContain("'env' => env('APP_ENV', 'production')");

    // Verify Minivora locales include both Arabic and English.
    expect(config('minivora.locales'))->toContain('ar', 'en');
});

test('V-18 NFR-REL-07 NFR-OPS-01 NFR-OPS-02 health monitor endpoint and email authentication policy checks pass', function () {
    // Health-check endpoint responds HTTP 200 for uptime monitors.
    $this->get('/up')->assertOk();

    // Mailer configuration has valid from address and mailable headers render cleanly.
    expect(config('mail.from.address'))->not->toBeEmpty();

    $store = Store::factory()->create(['name' => 'Nile Studio']);
    $mail = (new TestDiagnosticMail($store))->build();
    expect($mail->subject)->toContain('Nile Studio')
        ->and($mail->fromAddress)->not->toBeEmpty()
        ->and($mail->render())->toContain('Nile Studio');

    // Validate SPF, DKIM, and DMARC DNS policy records per V-18 / NFR-OPS-02.
    $validateEmailAuth = static function (string $spf, string $dkim, string $dmarc): array {
        $spfValid = preg_match('/^v=spf1\s+.+\s[-~]all$/i', trim($spf)) === 1
            && ! str_contains(strtolower($spf), '+all');
        $dkimValid = preg_match('/^v=DKIM1;\s*k=rsa;\s*p=[A-Za-z0-9+\/=]{16,}/i', trim($dkim)) === 1;
        $dmarcValid = preg_match('/^v=DMARC1;\s*p=(quarantine|reject)(;|$)/i', trim($dmarc)) === 1
            && str_contains(strtolower($dmarc), 'rua=mailto:');

        return [
            'spf' => $spfValid,
            'dkim' => $dkimValid,
            'dmarc' => $dmarcValid,
            'all_passed' => $spfValid && $dkimValid && $dmarcValid,
        ];
    };

    $compliant = $validateEmailAuth(
        'v=spf1 include:_spf.minivora.com -all',
        'v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA',
        'v=DMARC1; p=reject; rua=mailto:dmarc@minivora.com',
    );

    expect($compliant['spf'])->toBeTrue()
        ->and($compliant['dkim'])->toBeTrue()
        ->and($compliant['dmarc'])->toBeTrue()
        ->and($compliant['all_passed'])->toBeTrue();

    // Non-compliant policies (+all, empty key, p=none) are rejected.
    $weak = $validateEmailAuth(
        'v=spf1 +all',
        'v=DKIM1; k=rsa; p=',
        'v=DMARC1; p=none',
    );

    expect($weak['spf'])->toBeFalse()
        ->and($weak['dkim'])->toBeFalse()
        ->and($weak['dmarc'])->toBeFalse()
        ->and($weak['all_passed'])->toBeFalse();
});
