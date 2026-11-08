<?php

namespace App\Providers;

use App\Ai\Registry\FunctionRegistry;
use App\Core\AuditLog\ActivityLogger;
use App\Core\Tenancy\CurrentStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentStore::class);

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
    }
}
