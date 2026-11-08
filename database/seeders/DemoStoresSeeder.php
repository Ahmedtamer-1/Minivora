<?php

namespace Database\Seeders;

use App\Core\Permissions\Role;
use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local and demo data: a platform admin and five demo stores (SRS §2.4).
 * Products (200 per store) are added here once the Catalog module ships.
 * Every seeded account uses the password "password"; never run this in production.
 */
class DemoStoresSeeder extends Seeder
{
    /** @var list<array{name: string, subdomain: string, locale: string, sells: string, live: bool}> */
    private const STORES = [
        ['name' => 'Minivora Demo', 'subdomain' => 'demo', 'locale' => 'en', 'sells' => 'A bit of everything, for trying Minivora out', 'live' => true],
        ['name' => 'حرف النيل', 'subdomain' => 'nile-crafts', 'locale' => 'ar', 'sells' => 'فخار وأعمال يدوية من صعيد مصر', 'live' => true],
        ['name' => 'Cairo Leather Co.', 'subdomain' => 'cairo-leather', 'locale' => 'en', 'sells' => 'Handmade leather bags and wallets', 'live' => true],
        ['name' => 'بن الصباح', 'subdomain' => 'sabah-coffee', 'locale' => 'ar', 'sells' => 'بن مطحون طازة وتوابل', 'live' => false],
        ['name' => 'Alex Tech', 'subdomain' => 'alex-tech', 'locale' => 'en', 'sells' => 'Phone accessories and chargers', 'live' => false],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoStoresSeeder must not run in production.');
        }

        $admin = User::query()->firstOrCreate(['email' => 'admin@minivora.test'], [
            'name' => 'Platform Admin', 'password' => 'password', 'locale' => 'en',
        ]);
        $admin->forceFill(['is_platform_admin' => true, 'email_verified_at' => now()])->save();

        foreach (self::STORES as $i => $data) {
            $owner = User::query()->firstOrCreate(['email' => "owner{$i}@minivora.test"], [
                'name' => $data['name'].' Owner', 'password' => 'password', 'locale' => $data['locale'],
            ]);
            $owner->forceFill(['email_verified_at' => now()])->save();

            if (Store::query()->where('subdomain', $data['subdomain'])->exists()) {
                continue;
            }

            Store::factory()->withMember($owner, Role::Owner)->create([
                'name' => $data['name'],
                'subdomain' => $data['subdomain'],
                'default_locale' => $data['locale'],
                'profile' => ['sells' => $data['sells'], 'location' => 'Cairo', 'language' => $data['locale']],
                'onboarding_completed_at' => now(),
                'published_at' => $data['live'] ? now() : null,
            ]);
        }
    }
}
