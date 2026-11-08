<?php

namespace App\Modules\Catalog\Setup;

use App\Core\Tenancy\CurrentStore;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Setup\SetupStep;

/** Setup checklist: done once the store has at least one product. FR-ONB-10 */
class AddProductsStep implements SetupStep
{
    public function key(): string
    {
        return 'add_products';
    }

    public function isComplete(Store $store): bool
    {
        return app(CurrentStore::class)->runAs($store, fn () => Product::query()->exists());
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function route(): ?string
    {
        return 'products.create';
    }
}
