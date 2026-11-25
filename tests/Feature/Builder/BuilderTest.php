<?php

use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use App\Modules\Builder\Events\PagePublished;
use App\Modules\Builder\Http\Controllers\MediaController;
use App\Modules\Builder\Http\Controllers\PageController;
use App\Modules\Builder\Http\Controllers\ThemeController;
use App\Modules\Builder\Models\MediaFile;
use App\Modules\Builder\Models\Page;
use App\Modules\Builder\Models\PageVersion;
use App\Modules\Builder\Models\Section;
use App\Modules\Builder\Services\MediaService;
use App\Modules\Builder\Services\PageBuilderService;
use App\Modules\Builder\Services\SectionRegistry;
use App\Modules\Builder\Services\ThemeService;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Platform\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

function builderStore(): array
{
    $owner = User::factory()->create();
    $store = Store::factory()->withMember($owner)->create();
    app(CurrentStore::class)->set($store);

    return [$store, $owner];
}

test('FR-BLD-01 section registry defines all 15 SRS section types with defaults and validation', function () {
    $registry = app(SectionRegistry::class);

    $expectedTypes = [
        'hero',
        'video_hero',
        'featured_products',
        'category_grid',
        'image_banner',
        'image_carousel',
        'brand_story',
        'text_block',
        'video',
        'video_gallery',
        'video_reels',
        'social_carousel',
        'logo_carousel',
        'testimonials',
        'testimonial_carousel',
    ];

    // The 15 SRS types are all present (the library adds more: slideshow, marquee, custom…).
    expect(array_values(array_intersect(array_keys($registry->all()), $expectedTypes)))->toBe($expectedTypes)
        ->and(SectionRegistry::SRS_TYPES)->toBe($expectedTypes);

    foreach ($expectedTypes as $type) {
        expect($registry->isValid($type))->toBeTrue()
            ->and($registry->defaults($type))->toBeArray()->not->toBeEmpty();
    }

    expect($registry->isValid('nonexistent_section'))->toBeFalse();
    expect(fn () => $registry->defaults('nonexistent_section'))->toThrow(InvalidArgumentException::class);
});

test('FR-BLD-02 page builder adds, updates, toggles visibility, reorders, and deletes sections', function () {
    builderStore();
    $builder = app(PageBuilderService::class);

    $page = $builder->createPage([
        'title' => 'About Us',
        'title_ar' => 'عن المتجر',
        'slug' => 'about-us',
    ]);

    expect($page->status)->toBe(Page::STATUS_DRAFT)
        ->and($page->slug)->toBe('about-us');

    $s1 = $builder->addSection($page, 'hero', ['headline' => 'Hello Cairo']);
    $s2 = $builder->addSection($page, 'brand_story', ['body' => 'Our journey']);
    $s3 = $builder->addSection($page, 'testimonials');

    expect($s1->sort_order)->toBe(0)
        ->and($s1->settings['headline'])->toBe('Hello Cairo')
        ->and($s1->settings['cta_label'])->toBe('Shop Now')
        ->and($s2->sort_order)->toBe(1)
        ->and($s3->sort_order)->toBe(2);

    expect(fn () => $builder->addSection($page, 'invalid_type'))
        ->toThrow(InvalidArgumentException::class);

    $updated = $builder->updateSection($s1, ['headline' => 'Updated Hero']);
    expect($updated->settings['headline'])->toBe('Updated Hero')
        ->and($updated->settings['cta_label'])->toBe('Shop Now');

    $hidden = $builder->toggleSectionVisibility($s2);
    expect($hidden->is_visible)->toBeFalse();

    $shown = $builder->toggleSectionVisibility($s2);
    expect($shown->is_visible)->toBeTrue();

    $reordered = $builder->reorderSections($page, [$s3->id, $s1->id, $s2->id]);
    expect($reordered->pluck('id')->all())->toBe([$s3->id, $s1->id, $s2->id])
        ->and($reordered->pluck('sort_order')->all())->toBe([0, 1, 2]);

    $builder->deleteSection($s2);
    expect(Section::query()->where('page_id', $page->id)->count())->toBe(2);
});

test('FR-BLD-03 theme service manages defaults, updates, CSS variables, and validation', function () {
    builderStore();
    $themes = app(ThemeService::class);

    $default = $themes->current();
    expect($default->primary_color)->toBe('#111827')
        ->and($default->accent_color)->toBe('#2563eb')
        ->and($default->font_heading)->toBe('Cairo')
        ->and($default->font_body)->toBe('Cairo')
        ->and($default->corner_radius)->toBe('md')
        ->and($default->color_mode)->toBe('light');

    $updated = $themes->update([
        'primary_color' => '#0F172A',
        'accent_color' => '#E11D48',
        'font_heading' => 'Amiri',
        'font_body' => 'Tajawal',
        'corner_radius' => 'lg',
        'color_mode' => 'dark',
    ]);

    expect($updated->id)->toBe($default->id)
        ->and($updated->primary_color)->toBe('#0f172a')
        ->and($updated->accent_color)->toBe('#e11d48')
        ->and($updated->font_heading)->toBe('Amiri')
        ->and($updated->font_body)->toBe('Tajawal')
        ->and($updated->corner_radius)->toBe('lg')
        ->and($updated->color_mode)->toBe('dark')
        ->and($updated->cssVariables())->toBe([
            '--color-primary' => '#0f172a',
            '--color-accent' => '#e11d48',
            '--font-heading' => 'Amiri',
            '--font-body' => 'Tajawal',
            '--radius' => '1rem',
            '--color-mode' => 'dark',
        ]);

    expect(fn () => $themes->update(['primary_color' => 'red']))->toThrow(InvalidArgumentException::class);
    expect(fn () => $themes->update(['corner_radius' => 'giant']))->toThrow(InvalidArgumentException::class);
    expect(fn () => $themes->update(['color_mode' => 'sepia']))->toThrow(InvalidArgumentException::class);
});

test('FR-BLD-04 media service stores files with AR/EN alt text and supports bulk delete', function () {
    Storage::fake('public');
    builderStore();
    $media = app(MediaService::class);

    $f1 = $media->store(
        UploadedFile::fake()->image('hero.jpg', 800, 600),
        'Cairo Linen Shirt',
        'قميص كتان قاهري',
    );
    $f2 = $media->store(
        UploadedFile::fake()->image('banner.png', 400, 400),
        'Summer Banner',
        'بانر الصيف',
    );
    $f3 = $media->store(
        UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4'),
    );

    expect($f1->alt_en)->toBe('Cairo Linen Shirt')
        ->and($f1->alt_ar)->toBe('قميص كتان قاهري')
        ->and($f1->disk)->toBe('public')
        ->and($f1->size_bytes)->toBeGreaterThan(0);

    Storage::disk('public')->assertExists($f1->path);
    Storage::disk('public')->assertExists($f2->path);

    $deleted = $media->bulkDelete([$f1->id, $f2->id]);

    expect($deleted)->toBe(2)
        ->and(MediaFile::query()->count())->toBe(1)
        ->and(MediaFile::query()->first()->id)->toBe($f3->id);

    Storage::disk('public')->assertMissing($f1->path);
    Storage::disk('public')->assertMissing($f2->path);
    Storage::disk('public')->assertExists($f3->path);
});

test('FR-BLD-05 computePriceFromMargin enforces pure-code half-up formula and refuses invalid margins', function () {
    $builder = app(PageBuilderService::class);

    // 60.00 EGP cost with 40% margin => 100.00 EGP (10000 piasters)
    expect($builder->computePriceFromMargin(6000, 0.40))->toBe(10000)
        // 0% margin => cost price
        ->and($builder->computePriceFromMargin(4500, 0.0))->toBe(4500)
        // Half-up rounding: 100 / (1 - 1/3) = 150; 101 / 0.5 = 202; 101 / (1 - 0.33) = 150.746 -> 151
        ->and($builder->computePriceFromMargin(101, 0.33))->toBe(151)
        // Exact .5 rounds half up: 3 / (1 - 0.4) = 5; 5 / (1 - 0.6) = 12.5 -> 13
        ->and($builder->computePriceFromMargin(5, 0.60))->toBe(13);

    expect(fn () => $builder->computePriceFromMargin(1000, -0.01))->toThrow(InvalidArgumentException::class);
    expect(fn () => $builder->computePriceFromMargin(1000, 1.0))->toThrow(InvalidArgumentException::class);
    expect(fn () => $builder->computePriceFromMargin(1000, 1.25))->toThrow(InvalidArgumentException::class);
    expect(fn () => $builder->computePriceFromMargin(-100, 0.40))->toThrow(InvalidArgumentException::class);
});

test('FR-BLD-05 FR-BLD-06 AI draft creates draft homepage, theme, and code-priced draft products without auto-publishing', function () {
    Event::fake([PagePublished::class]);
    [, $owner] = builderStore();
    $builder = app(PageBuilderService::class);

    $draft = $builder->createAiDraft(
        'متجر ملابس كتان مصرية عصرية في القاهرة - Modern Egyptian linen clothing brand',
        [
            'title' => 'Nile Linen',
            'title_ar' => 'كتان النيل',
            'theme' => [
                'primary_color' => '#1e293b',
                'accent_color' => '#d97706',
                'corner_radius' => 'sm',
            ],
            'sections' => [
                ['type' => 'hero', 'settings' => ['headline' => 'Pure Egyptian Linen', 'headline_ar' => 'كتان مصري طبيعي']],
                ['type' => 'featured_products', 'settings' => ['limit' => 6]],
                ['type' => 'brand_story', 'settings' => ['heading' => 'Crafted in Cairo']],
            ],
            'products' => [
                [
                    'name' => 'Linen Overshirt',
                    'name_ar' => 'قميص كتان',
                    'unit_cost' => 60000, // 600 EGP
                    'price' => 999999,    // Must be ignored in favor of pure-code margin pricing
                    'stock' => 25,
                ],
                [
                    'name' => 'Linen Trousers',
                    'name_ar' => 'بنطلون كتان',
                    'unit_cost' => 45000, // 450 EGP
                    'target_margin' => 0.50,
                    'stock' => 15,
                ],
            ],
        ],
        0.40,
    );

    /** @var Page $page */
    $page = $draft['page'];
    expect($page->status)->toBe(Page::STATUS_DRAFT)
        ->and($page->is_ai_draft)->toBeTrue()
        ->and($page->isPublished())->toBeFalse()
        ->and($page->published_at)->toBeNull()
        ->and($page->sections)->toHaveCount(3)
        ->and($draft['theme']->primary_color)->toBe('#1e293b')
        ->and($draft['products'])->toHaveCount(2);

    // Pure-code pricing: 60000 / 0.60 = 100000; 45000 / 0.50 = 90000; all products stay draft
    expect($draft['products'][0]->price)->toBe(100000)
        ->and($draft['products'][0]->cost)->toBe(60000)
        ->and($draft['products'][0]->status)->toBe(ProductStatus::Draft)
        ->and($draft['products'][1]->price)->toBe(90000)
        ->and($draft['products'][1]->cost)->toBe(45000)
        ->and($draft['products'][1]->status)->toBe(ProductStatus::Draft);

    Event::assertNotDispatched(PagePublished::class);

    // Owner explicitly approves and publishes the draft
    $version = $builder->publishPage($page, $owner);

    expect($page->fresh()->status)->toBe(Page::STATUS_PUBLISHED)
        ->and($page->fresh()->published_at)->not->toBeNull()
        ->and($version->version_number)->toBe(1)
        ->and($version->published_by)->toBe($owner->id);

    Event::assertDispatched(PagePublished::class, fn (PagePublished $event) => $event->page->id === $page->id && $event->version->id === $version->id);
});

test('FR-BLD-07 publishing creates sequential version snapshots and restoreVersion rolls back sections', function () {
    Event::fake([PagePublished::class]);
    [, $owner] = builderStore();
    $builder = app(PageBuilderService::class);

    $page = $builder->createPage(['title' => 'Landing V1', 'slug' => 'landing']);
    $hero = $builder->addSection($page, 'hero', ['headline' => 'Version 1 Headline']);
    $story = $builder->addSection($page, 'brand_story', ['body' => 'Original story']);

    $v1 = $builder->publishPage($page, $owner);
    expect($v1->version_number)->toBe(1)
        ->and($v1->snapshot['sections'])->toHaveCount(2);

    // Modify page for version 2
    $page->title = 'Landing V2';
    $page->save();
    $builder->updateSection($hero, ['headline' => 'Version 2 Headline']);
    $builder->deleteSection($story);
    $builder->addSection($page, 'video_reels', ['heading' => 'New Reels']);

    $v2 = $builder->publishPage($page, $owner);
    expect($v2->version_number)->toBe(2)
        ->and(PageVersion::query()->where('page_id', $page->id)->count())->toBe(2);

    // Restore version 1
    $restored = $builder->restoreVersion($page, $v1);

    expect($restored->title)->toBe('Landing V1')
        ->and($restored->sections)->toHaveCount(2)
        ->and($restored->sections[0]->type)->toBe('hero')
        ->and($restored->sections[0]->settings['headline'])->toBe('Version 1 Headline')
        ->and($restored->sections[1]->type)->toBe('brand_story')
        ->and($restored->sections[1]->settings['body'])->toBe('Original story');
});

test('FR-BLD-08 starter templates support fashion, food, handmade, and electronics', function () {
    builderStore();
    $builder = app(PageBuilderService::class);
    $registry = app(SectionRegistry::class);

    foreach (['fashion', 'food', 'handmade', 'electronics'] as $businessType) {
        $result = $builder->applyStarterTemplate($businessType);

        expect($result['template'])->toBe($businessType)
            ->and($result['theme']->template)->toBe($businessType)
            ->and($result['page']->is_home)->toBeTrue()
            ->and($result['page']->sections->count())->toBeGreaterThanOrEqual(4);

        foreach ($result['page']->sections as $section) {
            expect($registry->isValid($section->type))->toBeTrue();
        }
    }

    expect(fn () => $builder->applyStarterTemplate('aerospace'))
        ->toThrow(InvalidArgumentException::class);
});

test('FR-BLD-02 FR-BLD-03 FR-BLD-04 builder controllers handle page, theme, and media requests', function () {
    Storage::fake('public');
    [, $owner] = builderStore();

    $pageController = app(PageController::class);
    $themeController = app(ThemeController::class);
    $mediaController = app(MediaController::class);

    $created = $pageController->store(Request::create('/pages', 'POST', [
        'title' => 'Home',
        'title_ar' => 'الرئيسية',
        'is_home' => true,
    ]));
    expect($created->getStatusCode())->toBe(201);
    $pageId = $created->getData(true)['page']['id'];

    $added = $pageController->addSection(Request::create("/pages/{$pageId}/sections", 'POST', [
        'type' => 'hero',
        'settings' => ['headline' => 'Controller Hero'],
    ]), $pageId);
    expect($added->getStatusCode())->toBe(201);

    $publishRequest = Request::create("/pages/{$pageId}/publish", 'POST');
    $publishRequest->setUserResolver(fn () => $owner);
    $published = $pageController->publish($publishRequest, $pageId);
    expect($published->getStatusCode())->toBe(200)
        ->and($published->getData(true)['page']['status'])->toBe(Page::STATUS_PUBLISHED);

    $themeRes = $themeController->update(Request::create('/theme', 'PATCH', [
        'primary_color' => '#123456',
        'color_mode' => 'dark',
    ]));
    expect($themeRes->getStatusCode())->toBe(200)
        ->and($themeRes->getData(true)['theme']['primary_color'])->toBe('#123456');

    $uploadReq = Request::create('/media', 'POST', [
        'alt_en' => 'Product Shot',
        'alt_ar' => 'صورة المنتج',
    ], [], [
        'file' => UploadedFile::fake()->image('shot.jpg'),
    ]);
    $uploadRes = $mediaController->store($uploadReq);
    expect($uploadRes->getStatusCode())->toBe(201);
    $mediaId = $uploadRes->getData(true)['file']['id'];

    $deleteRes = $mediaController->bulkDestroy(Request::create('/media/bulk-delete', 'DELETE', [
        'ids' => [$mediaId],
    ]));
    expect($deleteRes->getStatusCode())->toBe(200)
        ->and($deleteRes->getData(true)['deleted'])->toBe(1);
});
