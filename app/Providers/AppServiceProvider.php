<?php

namespace App\Providers;

use App\Ai\Registry\FunctionRegistry;
use App\Core\AuditLog\ActivityLogger;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Ledger\Listeners\PostOrderToLedger;
use App\Modules\Ledger\Services\ChartOfAccounts;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderReturned;
use App\Modules\Orders\Events\OrderShipped;
use App\Modules\Orders\Events\RefundIssued;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentStore::class);
        $this->app->scoped(ChartOfAccounts::class);

        $this->app->scoped(FunctionRegistry::class, fn ($app) => new FunctionRegistry(
            config('minivora.ai.functions', []),
            $app->make(CurrentStore::class),
            $app->make(ActivityLogger::class),
        ));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Vite::prefetch(concurrency: 3);

        // Orders → Ledger (Documentation §5.12). Module listeners are registered here, not discovered.
        Event::listen([OrderPaid::class, OrderShipped::class, OrderCancelled::class, OrderReturned::class, RefundIssued::class], PostOrderToLedger::class);
    }
}
