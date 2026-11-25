<?php

namespace App\Modules\Builder\Services;

use InvalidArgumentException;

/**
 * Registry of all 15 supported storefront section types and their default settings.
 * FR-BLD-01
 */
class SectionRegistry
{
    /**
     * @var array<string, array{label: string, label_ar: string, category: string, defaults: array<string, mixed>}>
     */
    private const DEFINITIONS = [
        'hero' => [
            'label' => 'Hero Banner',
            'label_ar' => 'بانر رئيسي',
            'category' => 'hero',
            'defaults' => [
                'headline' => '',
                'headline_ar' => '',
                'subheadline' => '',
                'subheadline_ar' => '',
                'cta_label' => 'Shop Now',
                'cta_label_ar' => 'تسوق الآن',
                'cta_url' => '/products',
                'image' => null,
                'alignment' => 'center',
            ],
        ],
        'video_hero' => [
            'label' => 'Video Hero',
            'label_ar' => 'بانر فيديو رئيسي',
            'category' => 'hero',
            'defaults' => [
                'headline' => '',
                'headline_ar' => '',
                'video_url' => null,
                'poster_image' => null,
                'autoplay' => true,
                'muted' => true,
                'loop' => true,
                'cta_label' => 'Explore',
                'cta_label_ar' => 'استكشف',
                'cta_url' => '/products',
            ],
        ],
        'featured_products' => [
            'label' => 'Featured Products',
            'label_ar' => 'منتجات مميزة',
            'category' => 'catalog',
            'defaults' => [
                'heading' => 'Featured Products',
                'heading_ar' => 'منتجات مميزة',
                'product_ids' => [],
                'limit' => 8,
                'columns' => 4,
            ],
        ],
        'category_grid' => [
            'label' => 'Category Grid',
            'label_ar' => 'شبكة الفئات',
            'category' => 'catalog',
            'defaults' => [
                'heading' => 'Shop by Category',
                'heading_ar' => 'تسوق حسب الفئة',
                'category_ids' => [],
                'columns' => 4,
            ],
        ],
        'image_banner' => [
            'label' => 'Image Banner',
            'label_ar' => 'بانر صورة',
            'category' => 'media',
            'defaults' => [
                'image' => null,
                'alt_en' => '',
                'alt_ar' => '',
                'caption' => '',
                'caption_ar' => '',
                'link_url' => null,
            ],
        ],
        'image_carousel' => [
            'label' => 'Image Carousel',
            'label_ar' => 'عرض صور متحرك',
            'category' => 'media',
            'defaults' => [
                'slides' => [],
                'autoplay' => true,
                'interval_ms' => 5000,
            ],
        ],
        'brand_story' => [
            'label' => 'Brand Story',
            'label_ar' => 'قصة العلامة التجارية',
            'category' => 'content',
            'defaults' => [
                'heading' => 'Our Story',
                'heading_ar' => 'قصتنا',
                'body' => '',
                'body_ar' => '',
                'image' => null,
                'image_position' => 'right',
            ],
        ],
        'text_block' => [
            'label' => 'Text Block',
            'label_ar' => 'كتلة نصية',
            'category' => 'content',
            'defaults' => [
                'heading' => '',
                'heading_ar' => '',
                'body' => '',
                'body_ar' => '',
                'alignment' => 'start',
            ],
        ],
        'video' => [
            'label' => 'Video',
            'label_ar' => 'فيديو',
            'category' => 'video',
            'defaults' => [
                'heading' => '',
                'heading_ar' => '',
                'video_url' => null,
                'poster_image' => null,
                'aspect_ratio' => '16:9',
            ],
        ],
        'video_gallery' => [
            'label' => 'Video Gallery',
            'label_ar' => 'معرض فيديوهات',
            'category' => 'video',
            'defaults' => [
                'heading' => 'Videos',
                'heading_ar' => 'فيديوهات',
                'videos' => [],
                'columns' => 3,
            ],
        ],
        'video_reels' => [
            'label' => 'Video Reels',
            'label_ar' => 'فيديوهات قصيرة',
            'category' => 'video',
            'defaults' => [
                'heading' => 'Reels',
                'heading_ar' => 'مقاطع قصيرة',
                'reels' => [],
                'autoplay_preview' => true,
            ],
        ],
        'social_carousel' => [
            'label' => 'Social Carousel',
            'label_ar' => 'عرض منشورات التواصل',
            'category' => 'social',
            'defaults' => [
                'heading' => 'Follow Us',
                'heading_ar' => 'تابعنا',
                'handle' => '',
                'posts' => [],
            ],
        ],
        'logo_carousel' => [
            'label' => 'Logo Carousel',
            'label_ar' => 'عرض الشعارات',
            'category' => 'social',
            'defaults' => [
                'heading' => 'As Seen In',
                'heading_ar' => 'شركاؤنا',
                'logos' => [],
            ],
        ],
        'testimonials' => [
            'label' => 'Testimonials',
            'label_ar' => 'آراء العملاء',
            'category' => 'social',
            'defaults' => [
                'heading' => 'What Customers Say',
                'heading_ar' => 'آراء عملائنا',
                'items' => [],
                'columns' => 3,
            ],
        ],
        'testimonial_carousel' => [
            'label' => 'Testimonial Carousel',
            'label_ar' => 'عرض متحرك لآراء العملاء',
            'category' => 'social',
            'defaults' => [
                'heading' => 'Customer Stories',
                'heading_ar' => 'تجارب العملاء',
                'items' => [],
                'autoplay' => true,
            ],
        ],
    ];

    /**
     * All registered section definitions keyed by section type.
     *
     * @return array<string, array{label: string, label_ar: string, category: string, defaults: array<string, mixed>}>
     */
    public function all(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public function isValid(string $type): bool
    {
        return array_key_exists($type, self::DEFINITIONS);
    }

    /**
     * Default settings payload for a valid section type.
     *
     * @return array<string, mixed>
     */
    public function defaults(string $type): array
    {
        if (! $this->isValid($type)) {
            throw new InvalidArgumentException("Unknown section type: {$type}");
        }

        return self::DEFINITIONS[$type]['defaults'];
    }
}
