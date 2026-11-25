<?php

namespace App\Modules\Platform\Jobs;

use App\Core\Tenancy\CurrentStore;
use App\Modules\Builder\Services\PageBuilderService;
use App\Modules\Platform\Models\Store;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generates the initial draft homepage preview in the background after onboarding
 * without blocking the store owner's HTTP request. FR-ONB-05
 */
class BuildInitialStoreDraft implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $answers
     */
    public function __construct(
        public readonly Store $store,
        public readonly array $answers = [],
    ) {}

    public function handle(CurrentStore $current, PageBuilderService $builder): void
    {
        $current->runAs($this->store, function () use ($builder): void {
            $parts = array_filter([
                trim((string) ($this->answers['sells'] ?? '')),
                trim((string) ($this->answers['location'] ?? '')),
            ], fn (string $value): bool => $value !== '');

            $description = $parts !== [] ? implode(' — ', $parts) : $this->store->name;

            $spec = [
                'slug' => 'home',
                'title' => trim((string) ($this->answers['title'] ?? $this->store->name)),
                'title_ar' => trim((string) ($this->answers['title_ar'] ?? $this->store->name)),
                'products' => is_array($this->answers['products'] ?? null) ? $this->answers['products'] : [],
            ];

            if (is_array($this->answers['theme'] ?? null)) {
                $spec['theme'] = $this->answers['theme'];
            }

            if (is_array($this->answers['sections'] ?? null)) {
                $spec['sections'] = $this->answers['sections'];
            }

            $builder->createAiDraft($description, $spec);
        });
    }
}
