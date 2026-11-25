<?php

namespace App\Importers\Adorna\Jobs;

use App\Importers\Adorna\AdornaImporter;
use App\Modules\Platform\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Nightly one-way Adorna EG import job, protected against overlapping runs per store. NFR-REL-08
 */
class RunNightlyAdornaImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $dataset
     */
    public function __construct(
        public readonly int $storeId,
        public readonly array $dataset = [],
    ) {}

    /**
     * Prevent concurrent import runs for the same store. NFR-REL-08
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("adorna-import-store-{$this->storeId}"))->dontRelease(),
        ];
    }

    public function handle(AdornaImporter $importer): void
    {
        $store = Store::query()->find($this->storeId);
        if ($store === null) {
            return;
        }

        $importer->import($store, $this->dataset);
    }
}
