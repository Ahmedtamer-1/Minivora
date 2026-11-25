<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Core\Permissions\PermissionMatrix;
use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use App\Modules\Builder\Models\MediaFile;
use App\Modules\Builder\Models\Page;
use App\Modules\Builder\Services\PageBuilderService;
use App\Modules\Builder\Services\SectionRegistry;
use App\Modules\Builder\Services\ThemeService;
use App\Modules\Seo\Models\Redirect;
use App\Modules\Seo\Models\SeoSetting;
use App\Modules\Seo\Services\RedirectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inertia page controllers for Store Builder (pages, theme, media) and SEO dashboard views.
 */
class BuilderSeoPagesController extends Controller
{
    public function __construct(
        private readonly CurrentStore $current,
        private readonly PageBuilderService $builder,
        private readonly SectionRegistry $sections,
        private readonly ThemeService $themes,
        private readonly RedirectService $redirects,
    ) {}

    public function editor(): Response
    {
        $pages = Page::query()
            ->with(['sections', 'versions'])
            ->orderByDesc('is_home')
            ->orderBy('id')
            ->get();

        return Inertia::render('builder/editor', [
            'pages' => $pages,
            'sectionTypes' => $this->sections->all(),
            'templates' => PageBuilderService::STARTER_TEMPLATES,
        ]);
    }

    public function applyTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_type' => ['required', 'string', Rule::in(PageBuilderService::STARTER_TEMPLATES)],
        ]);

        $this->builder->applyStarterTemplate($data['business_type']);

        return back()->with('success', 'Starter template applied.');
    }

    public function theme(): Response
    {
        return Inertia::render('builder/theme', [
            'theme' => $this->themes->current(),
        ]);
    }

    public function media(): Response
    {
        $files = MediaFile::query()
            ->latest()
            ->get()
            ->map(fn (MediaFile $file): array => $file->toArray() + ['url' => $file->url()])
            ->values();

        return Inertia::render('builder/media', [
            'files' => $files,
        ]);
    }

    public function seo(Request $request): Response
    {
        $store = $this->current->getOrFail();
        /** @var User|null $user */
        $user = $request->user();
        $role = $user?->roleIn($store);

        return Inertia::render('seo/index', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'subdomain' => $store->subdomain,
                'url' => $store->url(),
                'status' => $store->status()->value,
                'role' => $role?->value,
                'permissions' => $role === null ? [] : PermissionMatrix::for($role),
                'domain' => (string) config('minivora.domain'),
            ],
            'redirects' => Redirect::query()->latest()->get(),
            'notFound' => $this->redirects->topNotFound(20)->values(),
            'settings' => SeoSetting::query()->first(),
        ]);
    }
}
