<?php

namespace App\Modules\Builder\Services;

use InvalidArgumentException;

/**
 * Schema-driven library of storefront sections (FR-BLD-01).
 *
 * Every section type declares its settings fields, the block types it can hold and its defaults.
 * The dashboard editor builds its forms from this schema and the storefront renders
 * `resources/views/storefront/sections/{type}.blade.php`. A field with `tr` has an Arabic twin
 * stored under `{id}_ar`. Every section also gets the shared style settings (see styleFields()) and
 * a scoped `custom_css` string. Blocks are stored as `settings.blocks = [{id, type, hidden, settings}]`.
 *
 * Groups: `template` sections go on pages; `header` and `footer` sections live on the store-wide
 * header and footer groups (system pages `__header` / `__footer`).
 */
class SectionRegistry
{
    /** The 15 section types named by FR-BLD-01, in SRS order. */
    public const SRS_TYPES = [
        'hero', 'video_hero', 'featured_products', 'category_grid', 'image_banner', 'image_carousel',
        'brand_story', 'text_block', 'video', 'video_gallery', 'video_reels', 'social_carousel',
        'logo_carousel', 'testimonials', 'testimonial_carousel',
    ];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $cache = null;

    /**
     * All section definitions keyed by type: label, label_ar, category, group, fields, blocks,
     * max_blocks and defaults.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $defs = [];
        foreach ($this->definitions() as $type => $def) {
            $def += ['group' => 'template', 'blocks' => [], 'max_blocks' => 0, 'legacy' => [], 'default_blocks' => [], 'description' => ''];
            $defaults = $def['legacy'];
            foreach ($def['fields'] as $field) {
                $defaults += self::fieldDefaults($field);
            }
            $defaults['blocks'] = array_map(fn (array $b) => [
                'id' => substr(md5($type.json_encode($b)), 0, 8),
                'type' => $b['type'],
                'hidden' => false,
                'settings' => $b['settings'] ?? [],
            ], $def['default_blocks']);
            $defaults['style'] = $def['style'] ?? [];
            $defaults['custom_css'] = '';
            $def['defaults'] = $defaults;
            unset($def['legacy'], $def['default_blocks'], $def['style']);
            $defs[$type] = $def;
        }

        return $this->cache = $defs;
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->all());
    }

    public function isValid(string $type): bool
    {
        return array_key_exists($type, $this->all());
    }

    /** @return array<string, mixed> */
    public function get(string $type): array
    {
        if (! $this->isValid($type)) {
            throw new InvalidArgumentException("Unknown section type: {$type}");
        }

        return $this->all()[$type];
    }

    /** @return array<string, mixed> */
    public function defaults(string $type): array
    {
        return $this->get($type)['defaults'];
    }

    /** Which group (template, header, footer) a section type belongs to. */
    public function group(string $type): string
    {
        return (string) $this->get($type)['group'];
    }

    /** @return list<array<string, mixed>> Shared style settings shown on every section's Style tab. */
    public static function styleFields(): array
    {
        return [
            self::f('width', 'select', 'Width', 'العرض', ['default' => 'contained', 'options' => ['contained' => 'Contained', 'narrow' => 'Narrow', 'full' => 'Full width']]),
            self::f('padding_top', 'range', 'Top spacing', 'المسافة العلوية', ['default' => 48, 'min' => 0, 'max' => 240, 'step' => 4, 'unit' => 'px']),
            self::f('padding_bottom', 'range', 'Bottom spacing', 'المسافة السفلية', ['default' => 48, 'min' => 0, 'max' => 240, 'step' => 4, 'unit' => 'px']),
            self::f('background', 'color', 'Background', 'الخلفية', ['default' => '']),
            self::f('text_color', 'color', 'Text colour', 'لون النص', ['default' => '']),
            self::f('background_image', 'image', 'Background image', 'صورة الخلفية'),
            self::f('text_align', 'select', 'Text alignment', 'محاذاة النص', ['default' => '', 'options' => ['' => 'Default', 'start' => 'Start', 'center' => 'Center', 'end' => 'End']]),
            self::f('animation', 'select', 'Reveal on scroll', 'ظهور عند التمرير', ['default' => 'none', 'options' => ['none' => 'None', 'fade' => 'Fade', 'fade-up' => 'Fade up', 'zoom' => 'Zoom']]),
            self::f('hide_on', 'select', 'Hide on', 'إخفاء على', ['default' => '', 'options' => ['' => 'Never', 'mobile' => 'Mobile', 'desktop' => 'Desktop']]),
            self::f('anchor', 'text', 'Anchor ID (for #links)', 'معرّف الرابط'),
            self::f('custom_class', 'text', 'Extra CSS classes', 'أصناف CSS إضافية'),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function f(string $id, string $type, string $label, string $labelAr, array $extra = []): array
    {
        return ['id' => $id, 'type' => $type, 'label' => $label, 'label_ar' => $labelAr] + $extra;
    }

    /**
     * Translatable field: stored under `$id` (English) and `{$id}_ar` (Arabic).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function t(string $id, string $type, string $label, string $labelAr, string $default = '', string $defaultAr = '', array $extra = []): array
    {
        return self::f($id, $type, $label, $labelAr, ['tr' => true, 'default' => $default, 'default_ar' => $defaultAr] + $extra);
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private static function fieldDefaults(array $field): array
    {
        $default = $field['default'] ?? match ($field['type']) {
            'checkbox' => false,
            'range', 'number' => $field['min'] ?? 0,
            'product_list', 'category_list' => [],
            'image', 'video', 'category', 'product', 'datetime' => null,
            default => '',
        };
        $out = [$field['id'] => $default];
        if (! empty($field['tr'])) {
            $out[$field['id'].'_ar'] = $field['default_ar'] ?? '';
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private static function block(string $label, string $labelAr, array $fields): array
    {
        return ['label' => $label, 'label_ar' => $labelAr, 'fields' => $fields];
    }

    /** @return array<string, array<string, mixed>> */
    private function definitions(): array
    {
        $heading = fn (string $en = '', string $ar = '') => self::t('heading', 'text', 'Heading', 'العنوان', $en, $ar);
        $subheading = fn (string $en = '', string $ar = '') => self::t('subheading', 'textarea', 'Subheading', 'العنوان الفرعي', $en, $ar);
        $button = [
            self::t('button_label', 'text', 'Button label', 'نص الزر'),
            self::f('button_url', 'url', 'Button link', 'رابط الزر'),
        ];
        $height = self::f('height', 'select', 'Height', 'الارتفاع', ['default' => 'large', 'options' => ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large', 'full' => 'Full screen', 'auto' => 'Fit content']]);
        $overlay = self::f('overlay_opacity', 'range', 'Image overlay', 'تعتيم الصورة', ['default' => 30, 'min' => 0, 'max' => 90, 'step' => 5, 'unit' => '%']);
        $columns = fn (int $d = 4, int $max = 6) => self::f('columns', 'range', 'Columns on desktop', 'الأعمدة', ['default' => $d, 'min' => 1, 'max' => $max, 'step' => 1]);
        $mobileColumns = self::f('mobile_columns', 'range', 'Columns on mobile', 'الأعمدة على الجوال', ['default' => 2, 'min' => 1, 'max' => 2, 'step' => 1]);
        $ratio = fn (string $d = 'portrait') => self::f('aspect', 'select', 'Image shape', 'شكل الصورة', ['default' => $d, 'options' => ['square' => 'Square', 'portrait' => 'Portrait', 'landscape' => 'Landscape', 'tall' => 'Tall', 'auto' => 'Original']]);
        $content = self::f('content_position', 'select', 'Content position', 'موضع المحتوى', ['default' => 'center', 'options' => ['top-start' => 'Top start', 'top' => 'Top center', 'start' => 'Middle start', 'center' => 'Middle center', 'end' => 'Middle end', 'bottom-start' => 'Bottom start', 'bottom' => 'Bottom center', 'bottom-end' => 'Bottom end']]);
        $layoutGridCarousel = self::f('layout', 'select', 'Layout', 'التخطيط', ['default' => 'grid', 'options' => ['grid' => 'Grid', 'carousel' => 'Swipe carousel']]);
        $autoplay = self::f('autoplay', 'checkbox', 'Auto-rotate', 'تبديل تلقائي', ['default' => true]);
        $interval = self::f('interval_ms', 'range', 'Seconds per slide', 'ثوانٍ لكل شريحة', ['default' => 5000, 'min' => 2000, 'max' => 12000, 'step' => 500, 'unit' => 'ms']);

        $testimonialBlock = self::block('Testimonial', 'رأي عميل', [
            self::t('quote', 'textarea', 'Quote', 'الرأي'),
            self::f('author', 'text', 'Name', 'الاسم'),
            self::t('role', 'text', 'Detail (city, product…)', 'تفاصيل'),
            self::f('rating', 'range', 'Stars', 'النجوم', ['default' => 5, 'min' => 0, 'max' => 5, 'step' => 1]),
            self::f('image', 'image', 'Photo', 'صورة'),
        ]);

        $richBlocks = [
            'heading' => self::block('Heading', 'عنوان', [
                self::t('text', 'text', 'Text', 'النص', 'Heading'),
                self::f('tag', 'select', 'Level', 'المستوى', ['default' => 'h2', 'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'Paragraph']]),
                self::f('size', 'range', 'Size', 'الحجم', ['default' => 40, 'min' => 12, 'max' => 160, 'step' => 2, 'unit' => 'px']),
                self::f('uppercase', 'checkbox', 'Uppercase', 'أحرف كبيرة'),
                self::f('letter_spacing', 'range', 'Letter spacing', 'تباعد الأحرف', ['default' => 0, 'min' => -5, 'max' => 40, 'step' => 1, 'unit' => '%']),
            ]),
            'text' => self::block('Text', 'نص', [self::t('text', 'richtext', 'Text', 'النص')]),
            'button' => self::block('Button', 'زر', [
                self::t('label', 'text', 'Label', 'النص', 'Shop now', 'تسوق الآن'),
                self::f('url', 'url', 'Link', 'الرابط', ['default' => '/shop']),
                self::f('style', 'select', 'Style', 'النمط', ['default' => 'primary', 'options' => ['primary' => 'Solid', 'outline' => 'Outline', 'link' => 'Text link']]),
            ]),
            'image' => self::block('Image', 'صورة', [
                self::f('image', 'image', 'Image', 'الصورة'),
                self::f('alt', 'text', 'Alt text', 'نص بديل'),
                $ratio('auto'),
                self::f('link_url', 'url', 'Link', 'الرابط'),
            ]),
            'video' => self::block('Video', 'فيديو', [
                self::f('video_url', 'video', 'Video (file or YouTube/Vimeo link)', 'الفيديو'),
                self::f('poster_image', 'image', 'Cover image', 'صورة الغلاف'),
            ]),
            'product' => self::block('Product card', 'بطاقة منتج', [self::f('product_id', 'product', 'Product', 'المنتج')]),
            'countdown' => self::block('Countdown', 'عدّاد تنازلي', [self::f('ends_at', 'datetime', 'Ends at', 'ينتهي في')]),
            'badge' => self::block('Badge', 'شارة', [self::t('text', 'text', 'Text', 'النص', 'New', 'جديد')]),
            'spacer' => self::block('Spacer', 'مسافة', [self::f('height', 'range', 'Height', 'الارتفاع', ['default' => 24, 'min' => 0, 'max' => 200, 'step' => 4, 'unit' => 'px'])]),
            'divider' => self::block('Divider', 'فاصل', []),
            'html' => self::block('Custom HTML', 'HTML مخصص', [self::f('code', 'code', 'HTML', 'HTML', ['language' => 'html'])]),
        ];

        return [
            // ───────────── Header group ─────────────
            'announcement_bar' => [
                'label' => 'Announcement bar', 'label_ar' => 'شريط الإعلانات', 'category' => 'header', 'group' => 'header',
                'description' => 'Rotating messages above the header.',
                'fields' => [$autoplay, $interval],
                'blocks' => ['announcement' => self::block('Announcement', 'إعلان', [self::t('text', 'text', 'Text', 'النص', 'Free delivery over 1,000 EGP', 'توصيل مجاني للطلبات فوق ١٠٠٠ ج.م'), self::f('link_url', 'url', 'Link', 'الرابط')])],
                'max_blocks' => 8,
                'default_blocks' => [['type' => 'announcement', 'settings' => ['text' => 'Free delivery over 1,000 EGP', 'text_ar' => 'توصيل مجاني للطلبات فوق ١٠٠٠ ج.م']]],
                'style' => ['padding_top' => 8, 'padding_bottom' => 8, 'width' => 'full'],
            ],
            'header' => [
                'label' => 'Header', 'label_ar' => 'رأس الصفحة', 'category' => 'header', 'group' => 'header',
                'description' => 'Logo, menu with dropdowns, search, cart and language switch.',
                'fields' => [
                    self::f('logo', 'image', 'Logo', 'الشعار'),
                    self::f('logo_width', 'range', 'Logo width', 'عرض الشعار', ['default' => 120, 'min' => 40, 'max' => 320, 'step' => 4, 'unit' => 'px']),
                    self::t('tagline', 'text', 'Tagline next to logo', 'شعار نصي'),
                    self::f('layout', 'select', 'Layout', 'التخطيط', ['default' => 'logo-start', 'options' => ['logo-start' => 'Logo start, menu center', 'logo-center' => 'Logo center', 'logo-start-menu-start' => 'Logo and menu start']]),
                    self::f('sticky', 'checkbox', 'Stick to top on scroll', 'تثبيت أعلى الصفحة', ['default' => true]),
                    self::f('transparent_on_home', 'checkbox', 'Transparent over the first section on the home page', 'شفاف فوق أول قسم', ['default' => false]),
                    self::f('uppercase_menu', 'checkbox', 'Uppercase menu', 'قائمة بأحرف كبيرة', ['default' => false]),
                    self::f('show_search', 'checkbox', 'Search', 'البحث', ['default' => true]),
                    self::f('show_cart', 'checkbox', 'Cart', 'السلة', ['default' => true]),
                    self::f('show_language', 'checkbox', 'Language switch', 'تبديل اللغة', ['default' => true]),
                ],
                'blocks' => ['menu_link' => self::block('Menu item', 'عنصر قائمة', [
                    self::t('label', 'text', 'Label', 'النص', 'Shop', 'المتجر'),
                    self::f('url', 'url', 'Link', 'الرابط', ['default' => '/shop']),
                    self::f('highlight', 'checkbox', 'Highlight (e.g. Sale)', 'تمييز'),
                    self::t('children', 'links', 'Dropdown links — one per line: Label | /link', 'روابط القائمة المنسدلة'),
                ])],
                'max_blocks' => 12,
                'default_blocks' => [
                    ['type' => 'menu_link', 'settings' => ['label' => 'Shop', 'label_ar' => 'المتجر', 'url' => '/shop']],
                    ['type' => 'menu_link', 'settings' => ['label' => 'About', 'label_ar' => 'من نحن', 'url' => '/pages/about']],
                ],
                'style' => ['padding_top' => 16, 'padding_bottom' => 16, 'width' => 'full'],
            ],

            // ───────────── Template sections (FR-BLD-01 + extras) ─────────────
            'hero' => [
                'label' => 'Hero Banner', 'label_ar' => 'بانر رئيسي', 'category' => 'hero',
                'description' => 'Big image with headline and button.',
                'legacy' => ['cta_label' => 'Shop Now', 'cta_label_ar' => 'تسوق الآن', 'cta_url' => '/shop'],
                'fields' => [
                    self::f('image', 'image', 'Image', 'الصورة'),
                    self::f('mobile_image', 'image', 'Mobile image (optional)', 'صورة الجوال'),
                    self::t('eyebrow', 'text', 'Small text above', 'نص صغير أعلى'),
                    self::t('headline', 'text', 'Headline', 'العنوان'),
                    self::t('subheadline', 'textarea', 'Text', 'النص'),
                    self::t('cta_label', 'text', 'Button label', 'نص الزر', 'Shop Now', 'تسوق الآن'),
                    self::f('cta_url', 'url', 'Button link', 'رابط الزر', ['default' => '/shop']),
                    self::t('cta2_label', 'text', 'Second button label', 'نص الزر الثاني'),
                    self::f('cta2_url', 'url', 'Second button link', 'رابط الزر الثاني'),
                    $height, $content, $overlay,
                    self::f('headline_size', 'range', 'Headline size', 'حجم العنوان', ['default' => 64, 'min' => 24, 'max' => 200, 'step' => 2, 'unit' => 'px']),
                    self::f('alignment', 'select', 'Text alignment', 'المحاذاة', ['default' => 'center', 'options' => ['start' => 'Start', 'center' => 'Center', 'end' => 'End']]),
                ],
                'style' => ['width' => 'full', 'padding_top' => 0, 'padding_bottom' => 0],
            ],
            'video_hero' => [
                'label' => 'Video Hero', 'label_ar' => 'بانر فيديو رئيسي', 'category' => 'hero',
                'description' => 'Full-bleed background video with headline.',
                'fields' => [
                    self::f('video_url', 'video', 'Video (MP4 or YouTube/Vimeo link)', 'الفيديو'),
                    self::f('poster_image', 'image', 'Cover image', 'صورة الغلاف'),
                    self::t('headline', 'text', 'Headline', 'العنوان'),
                    self::t('subheadline', 'textarea', 'Text', 'النص'),
                    self::t('cta_label', 'text', 'Button label', 'نص الزر', 'Explore', 'استكشف'),
                    self::f('cta_url', 'url', 'Button link', 'رابط الزر', ['default' => '/shop']),
                    self::f('autoplay', 'checkbox', 'Autoplay', 'تشغيل تلقائي', ['default' => true]),
                    self::f('muted', 'checkbox', 'Muted', 'بدون صوت', ['default' => true]),
                    self::f('loop', 'checkbox', 'Loop', 'تكرار', ['default' => true]),
                    $height, $content, $overlay,
                ],
                'style' => ['width' => 'full', 'padding_top' => 0, 'padding_bottom' => 0],
            ],
            'slideshow' => [
                'label' => 'Slideshow', 'label_ar' => 'عرض شرائح', 'category' => 'hero',
                'description' => 'Rotating full-width banners, each with its own text, button and countdown.',
                'fields' => [$autoplay, $interval, $height, $overlay, self::f('show_arrows', 'checkbox', 'Arrows', 'أسهم', ['default' => true]), self::f('show_dots', 'checkbox', 'Dots', 'نقاط', ['default' => true])],
                'blocks' => ['slide' => self::block('Slide', 'شريحة', [
                    self::f('image', 'image', 'Image', 'الصورة'),
                    self::f('mobile_image', 'image', 'Mobile image', 'صورة الجوال'),
                    self::t('eyebrow', 'text', 'Small text above', 'نص صغير'),
                    self::t('heading', 'text', 'Heading', 'العنوان', 'New drop', 'إصدار جديد'),
                    self::t('text', 'textarea', 'Text', 'النص'),
                    self::t('button_label', 'text', 'Button label', 'نص الزر', 'Shop now', 'تسوق الآن'),
                    self::f('button_url', 'url', 'Button link', 'رابط الزر', ['default' => '/shop']),
                    self::f('countdown_to', 'datetime', 'Countdown to (optional)', 'عدّاد حتى'),
                    $content,
                ])],
                'max_blocks' => 10,
                'default_blocks' => [['type' => 'slide', 'settings' => ['heading' => 'New drop', 'heading_ar' => 'إصدار جديد', 'button_label' => 'Shop now', 'button_label_ar' => 'تسوق الآن', 'button_url' => '/shop', 'content_position' => 'bottom-start']], ['type' => 'slide', 'settings' => ['heading' => 'Archive sale', 'heading_ar' => 'تخفيضات الأرشيف', 'button_label' => 'View', 'button_label_ar' => 'شاهد', 'button_url' => '/shop', 'content_position' => 'bottom-start']]],
                'style' => ['width' => 'full', 'padding_top' => 0, 'padding_bottom' => 0],
            ],
            'featured_products' => [
                'label' => 'Featured Products', 'label_ar' => 'منتجات مميزة', 'category' => 'catalog',
                'description' => 'Product grid or carousel: hand-picked, a category, newest or best sellers.',
                'legacy' => ['product_ids' => []],
                'fields' => [
                    $heading('Featured Products', 'منتجات مميزة'), $subheading(),
                    self::f('source', 'select', 'Products', 'المنتجات', ['default' => 'newest', 'options' => ['newest' => 'Newest', 'best_selling' => 'Best selling', 'category' => 'From a category', 'manual' => 'Pick by hand', 'on_sale' => 'On sale']]),
                    self::f('category_id', 'category', 'Category', 'الفئة', ['show_if' => ['source' => 'category']]),
                    self::f('product_ids', 'product_list', 'Products', 'المنتجات', ['show_if' => ['source' => 'manual']]),
                    self::f('limit', 'range', 'How many', 'العدد', ['default' => 8, 'min' => 1, 'max' => 24, 'step' => 1]),
                    $columns(4), $mobileColumns, $layoutGridCarousel, $ratio('portrait'),
                    self::f('hover_swap', 'checkbox', 'Show second photo on hover', 'الصورة الثانية عند المرور', ['default' => true]),
                    self::f('show_view_all', 'checkbox', '"View all" link', 'رابط عرض الكل', ['default' => true]),
                    self::t('view_all_label', 'text', '"View all" text', 'نص عرض الكل', 'View all', 'عرض الكل'),
                ],
            ],
            'category_grid' => [
                'label' => 'Category Grid', 'label_ar' => 'شبكة الفئات', 'category' => 'catalog',
                'description' => 'Your categories as image cards.',
                'fields' => [
                    $heading('Shop by Category', 'تسوق حسب الفئة'), $subheading(),
                    self::f('category_ids', 'category_list', 'Categories (empty = all)', 'الفئات'),
                    $columns(4), $mobileColumns, $ratio('portrait'),
                    self::f('card_style', 'select', 'Card style', 'نمط البطاقة', ['default' => 'overlay', 'options' => ['overlay' => 'Text on image', 'below' => 'Text below']]),
                    self::f('numbered', 'checkbox', 'Number the cards (01, 02…)', 'ترقيم البطاقات'),
                ],
            ],
            'multicolumn' => [
                'label' => 'Cards / Columns', 'label_ar' => 'بطاقات وأعمدة', 'category' => 'content',
                'description' => 'Grid of image cards with numbers, titles, text and links.',
                'fields' => [$heading('', ''), $subheading(), $columns(3), $mobileColumns, $ratio('portrait'), $layoutGridCarousel,
                    self::f('card_style', 'select', 'Card style', 'نمط البطاقة', ['default' => 'below', 'options' => ['overlay' => 'Text on image', 'below' => 'Text below', 'plain' => 'No image frame']])],
                'blocks' => ['column' => self::block('Card', 'بطاقة', [
                    self::f('image', 'image', 'Image', 'الصورة'),
                    self::t('label', 'text', 'Small label (e.g. 01)', 'تسمية صغيرة'),
                    self::t('title', 'text', 'Title', 'العنوان', 'Card title', 'عنوان البطاقة'),
                    self::t('text', 'textarea', 'Text', 'النص'),
                    self::f('link_url', 'url', 'Link', 'الرابط'),
                ])],
                'max_blocks' => 12,
                'default_blocks' => [
                    ['type' => 'column', 'settings' => ['label' => '01', 'title' => 'First', 'title_ar' => 'الأول']],
                    ['type' => 'column', 'settings' => ['label' => '02', 'title' => 'Second', 'title_ar' => 'الثاني']],
                    ['type' => 'column', 'settings' => ['label' => '03', 'title' => 'Third', 'title_ar' => 'الثالث']],
                ],
            ],
            'image_banner' => [
                'label' => 'Image Banner', 'label_ar' => 'بانر صورة', 'category' => 'media',
                'description' => 'Full-bleed image with optional heading, badge and button (lookbook style).',
                'fields' => [
                    self::f('image', 'image', 'Image', 'الصورة'),
                    self::f('mobile_image', 'image', 'Mobile image', 'صورة الجوال'),
                    self::f('alt_en', 'text', 'Alt text (English)', 'نص بديل إنجليزي'),
                    self::f('alt_ar', 'text', 'Alt text (Arabic)', 'نص بديل عربي'),
                    self::t('badge', 'text', 'Badge (e.g. −35%)', 'شارة'),
                    $heading(), self::t('text', 'textarea', 'Text', 'النص'),
                    self::t('caption', 'text', 'Caption under image', 'تعليق'),
                    ...$button,
                    self::f('link_url', 'url', 'Make the whole image a link', 'رابط الصورة'),
                    $height, $content, $overlay,
                ],
                'style' => ['width' => 'full', 'padding_top' => 0, 'padding_bottom' => 0],
            ],
            'image_with_text' => [
                'label' => 'Image with text', 'label_ar' => 'صورة مع نص', 'category' => 'content',
                'description' => 'Split layout: image on one side, story on the other.',
                'fields' => [
                    self::f('image', 'image', 'Image', 'الصورة'),
                    self::f('image_position', 'select', 'Image side', 'جهة الصورة', ['default' => 'start', 'options' => ['start' => 'Start', 'end' => 'End']]),
                    self::f('image_width', 'select', 'Image width', 'عرض الصورة', ['default' => '50', 'options' => ['33' => 'One third', '50' => 'Half', '66' => 'Two thirds']]),
                    $ratio('portrait'),
                    self::t('big_text', 'text', 'Big display text (e.g. a word or year)', 'نص كبير'),
                    self::t('eyebrow', 'text', 'Small text above', 'نص صغير'),
                    $heading('Our story', 'قصتنا'),
                    self::t('body', 'richtext', 'Text', 'النص'),
                    ...$button,
                    self::f('vertical', 'select', 'Vertical alignment', 'المحاذاة العمودية', ['default' => 'center', 'options' => ['start' => 'Top', 'center' => 'Middle', 'end' => 'Bottom']]),
                ],
            ],
            'image_carousel' => [
                'label' => 'Image Carousel', 'label_ar' => 'عرض صور متحرك', 'category' => 'media',
                'description' => 'Swipeable row of images.',
                'legacy' => ['slides' => []],
                'fields' => [$heading(), $autoplay, $interval, self::f('per_view', 'range', 'Images visible', 'الصور الظاهرة', ['default' => 3, 'min' => 1, 'max' => 6, 'step' => 1]), $ratio('portrait')],
                'blocks' => ['slide' => self::block('Image', 'صورة', [self::f('image', 'image', 'Image', 'الصورة'), self::t('caption', 'text', 'Caption', 'تعليق'), self::f('link_url', 'url', 'Link', 'الرابط')])],
                'max_blocks' => 20,
            ],
            'brand_story' => [
                'label' => 'Brand Story', 'label_ar' => 'قصة العلامة التجارية', 'category' => 'content',
                'description' => 'Your story with a photo.',
                'fields' => [
                    $heading('Our Story', 'قصتنا'),
                    self::t('body', 'richtext', 'Story', 'القصة'),
                    self::f('image', 'image', 'Image', 'الصورة'),
                    self::f('image_position', 'select', 'Image side', 'جهة الصورة', ['default' => 'right', 'options' => ['left' => 'Left', 'right' => 'Right']]),
                    ...$button,
                ],
            ],
            'text_block' => [
                'label' => 'Text Block', 'label_ar' => 'كتلة نصية', 'category' => 'content',
                'description' => 'Heading and formatted text.',
                'fields' => [
                    $heading(), self::t('body', 'richtext', 'Text', 'النص'),
                    self::f('alignment', 'select', 'Alignment', 'المحاذاة', ['default' => 'start', 'options' => ['start' => 'Start', 'center' => 'Center', 'end' => 'End']]),
                    self::f('heading_size', 'range', 'Heading size', 'حجم العنوان', ['default' => 36, 'min' => 16, 'max' => 160, 'step' => 2, 'unit' => 'px']),
                    ...$button,
                ],
                'style' => ['width' => 'narrow'],
            ],
            'marquee' => [
                'label' => 'Scrolling text', 'label_ar' => 'نص متحرك', 'category' => 'content',
                'description' => 'Endless ticker of words — "Limited drops, no restocks."',
                'fields' => [
                    self::f('speed', 'range', 'Seconds per loop', 'ثوانٍ لكل دورة', ['default' => 30, 'min' => 5, 'max' => 120, 'step' => 1, 'unit' => 's']),
                    self::f('direction', 'select', 'Direction', 'الاتجاه', ['default' => 'forward', 'options' => ['forward' => 'Forward', 'reverse' => 'Reverse']]),
                    self::f('font_size', 'range', 'Text size', 'حجم النص', ['default' => 32, 'min' => 12, 'max' => 200, 'step' => 2, 'unit' => 'px']),
                    self::f('separator', 'text', 'Separator', 'الفاصل', ['default' => '✦']),
                    self::f('uppercase', 'checkbox', 'Uppercase', 'أحرف كبيرة', ['default' => true]),
                    self::f('outline', 'checkbox', 'Outlined letters', 'أحرف مفرغة'),
                    self::f('pause_on_hover', 'checkbox', 'Pause on hover', 'إيقاف عند المرور', ['default' => true]),
                ],
                'blocks' => ['item' => self::block('Text', 'نص', [self::t('text', 'text', 'Text', 'النص', 'Limited drops', 'إصدارات محدودة'), self::f('image', 'image', 'Small image (optional)', 'صورة صغيرة')])],
                'max_blocks' => 12,
                'default_blocks' => [['type' => 'item', 'settings' => ['text' => 'Limited drops', 'text_ar' => 'إصدارات محدودة']], ['type' => 'item', 'settings' => ['text' => 'No restocks', 'text_ar' => 'بدون إعادة تخزين']]],
                'style' => ['width' => 'full', 'padding_top' => 16, 'padding_bottom' => 16],
            ],
            'countdown_banner' => [
                'label' => 'Countdown / promo', 'label_ar' => 'عرض بعدّاد', 'category' => 'content',
                'description' => 'Sale banner with a live countdown.',
                'fields' => [
                    self::f('image', 'image', 'Image', 'الصورة'),
                    self::t('eyebrow', 'text', 'Small text above', 'نص صغير', 'Black Week', 'الأسبوع الأسود'),
                    $heading('−20% on everything', 'خصم ٢٠٪ على كل شيء'),
                    self::t('text', 'textarea', 'Text', 'النص'),
                    self::f('ends_at', 'datetime', 'Ends at', 'ينتهي في'),
                    self::t('expired_text', 'text', 'Text after it ends', 'النص بعد الانتهاء', 'This offer has ended', 'انتهى العرض'),
                    ...$button,
                    self::f('layout', 'select', 'Layout', 'التخطيط', ['default' => 'split', 'options' => ['split' => 'Image beside text', 'background' => 'Image behind text', 'text' => 'Text only']]),
                ],
            ],
            'video' => [
                'label' => 'Video', 'label_ar' => 'فيديو', 'category' => 'video',
                'description' => 'A single video from a file, YouTube or Vimeo.',
                'fields' => [
                    $heading(),
                    self::f('video_url', 'video', 'Video', 'الفيديو'),
                    self::f('poster_image', 'image', 'Cover image', 'صورة الغلاف'),
                    self::f('aspect_ratio', 'select', 'Shape', 'الشكل', ['default' => '16:9', 'options' => ['16:9' => '16:9', '4:3' => '4:3', '1:1' => '1:1', '9:16' => '9:16 (vertical)', '21:9' => '21:9 (cinema)']]),
                    self::f('autoplay', 'checkbox', 'Autoplay muted', 'تشغيل تلقائي'),
                ],
            ],
            'video_gallery' => [
                'label' => 'Video Gallery', 'label_ar' => 'معرض فيديوهات', 'category' => 'video',
                'description' => 'Grid of videos.',
                'legacy' => ['videos' => []],
                'fields' => [$heading('Videos', 'فيديوهات'), $columns(3, 4)],
                'blocks' => ['video' => self::block('Video', 'فيديو', [self::f('video_url', 'video', 'Video', 'الفيديو'), self::f('poster_image', 'image', 'Cover image', 'صورة الغلاف'), self::t('title', 'text', 'Title', 'العنوان')])],
                'max_blocks' => 12,
            ],
            'video_reels' => [
                'label' => 'Video Reels', 'label_ar' => 'فيديوهات قصيرة', 'category' => 'video',
                'description' => 'Vertical short videos that play on hover, linked to products.',
                'legacy' => ['reels' => [], 'autoplay_preview' => true],
                'fields' => [$heading('Reels', 'مقاطع قصيرة'), $subheading()],
                'blocks' => ['reel' => self::block('Reel', 'مقطع', [self::f('video_url', 'video', 'Video (MP4)', 'الفيديو'), self::f('poster_image', 'image', 'Cover', 'الغلاف'), self::t('caption', 'text', 'Caption', 'تعليق'), self::f('product_url', 'url', 'Product link', 'رابط المنتج')])],
                'max_blocks' => 12,
            ],
            'social_carousel' => [
                'label' => 'Social Carousel', 'label_ar' => 'عرض منشورات التواصل', 'category' => 'social',
                'description' => 'Customer photos or Instagram-style posts with handles.',
                'legacy' => ['posts' => []],
                'fields' => [$heading('Follow Us', 'تابعنا'), $subheading(), self::f('handle', 'text', 'Account handle (e.g. @yourstore)', 'الحساب'), self::f('profile_url', 'url', 'Profile link', 'رابط الحساب'), $columns(4), $layoutGridCarousel, $ratio('square')],
                'blocks' => ['post' => self::block('Post', 'منشور', [self::f('image', 'image', 'Image', 'الصورة'), self::f('handle', 'text', 'Handle', 'الحساب'), self::t('caption', 'text', 'Caption', 'تعليق'), self::f('link_url', 'url', 'Link', 'الرابط')])],
                'max_blocks' => 16,
            ],
            'logo_carousel' => [
                'label' => 'Logo Carousel', 'label_ar' => 'عرض الشعارات', 'category' => 'social',
                'description' => 'Press or partner logos, still or scrolling.',
                'legacy' => ['logos' => []],
                'fields' => [$heading('As Seen In', 'شركاؤنا'), self::f('grayscale', 'checkbox', 'Grey logos', 'شعارات رمادية', ['default' => true]), self::f('scrolling', 'checkbox', 'Scroll endlessly', 'تمرير مستمر', ['default' => true]), self::f('logo_height', 'range', 'Logo height', 'ارتفاع الشعار', ['default' => 40, 'min' => 16, 'max' => 120, 'step' => 2, 'unit' => 'px'])],
                'blocks' => ['logo' => self::block('Logo', 'شعار', [self::f('image', 'image', 'Logo', 'الشعار'), self::f('alt', 'text', 'Name', 'الاسم'), self::f('link_url', 'url', 'Link', 'الرابط')])],
                'max_blocks' => 20,
            ],
            'testimonials' => [
                'label' => 'Testimonials', 'label_ar' => 'آراء العملاء', 'category' => 'social',
                'description' => 'Customer quotes in a grid.',
                'legacy' => ['items' => []],
                'fields' => [$heading('What Customers Say', 'آراء عملائنا'), $columns(3, 4)],
                'blocks' => ['testimonial' => $testimonialBlock],
                'max_blocks' => 12,
            ],
            'testimonial_carousel' => [
                'label' => 'Testimonial Carousel', 'label_ar' => 'عرض متحرك لآراء العملاء', 'category' => 'social',
                'description' => 'One quote at a time, rotating.',
                'legacy' => ['items' => []],
                'fields' => [$heading('Customer Stories', 'تجارب العملاء'), $autoplay, $interval],
                'blocks' => ['testimonial' => $testimonialBlock],
                'max_blocks' => 12,
            ],
            'newsletter' => [
                'label' => 'Newsletter', 'label_ar' => 'النشرة البريدية', 'category' => 'content',
                'description' => 'Email sign-up with an offer.',
                'fields' => [
                    self::t('eyebrow', 'text', 'Small text above', 'نص صغير'),
                    $heading('Be first to know', 'كن أول من يعرف'),
                    self::t('text', 'textarea', 'Text', 'النص', '10% off your first order.', 'خصم ١٠٪ على أول طلب.'),
                    self::t('placeholder', 'text', 'Field placeholder', 'نص الحقل', 'Your email', 'بريدك الإلكتروني'),
                    self::t('button_label', 'text', 'Button', 'الزر', 'Subscribe', 'اشترك'),
                    self::f('layout', 'select', 'Layout', 'التخطيط', ['default' => 'center', 'options' => ['center' => 'Centered', 'split' => 'Text beside form']]),
                ],
            ],
            'faq' => [
                'label' => 'FAQ', 'label_ar' => 'الأسئلة الشائعة', 'category' => 'content',
                'description' => 'Questions that open to show answers.',
                'fields' => [$heading('Questions', 'أسئلة شائعة'), $subheading()],
                'blocks' => ['question' => self::block('Question', 'سؤال', [self::t('question', 'text', 'Question', 'السؤال', 'How long does delivery take?', 'كم يستغرق التوصيل؟'), self::t('answer', 'richtext', 'Answer', 'الإجابة')])],
                'max_blocks' => 30,
                'default_blocks' => [['type' => 'question', 'settings' => ['question' => 'How long does delivery take?', 'question_ar' => 'كم يستغرق التوصيل؟', 'answer' => '2–5 working days across Egypt.', 'answer_ar' => 'من ٢ إلى ٥ أيام عمل داخل مصر.']]],
                'style' => ['width' => 'narrow'],
            ],
            'custom_blocks' => [
                'label' => 'Custom section', 'label_ar' => 'قسم مخصص', 'category' => 'advanced',
                'description' => 'Build anything from blocks: headings, text, buttons, images, video, products, countdowns, HTML.',
                'fields' => [
                    self::f('layout', 'select', 'Layout', 'التخطيط', ['default' => 'stack', 'options' => ['stack' => 'Stack (one under another)', 'row' => 'Row (side by side, wraps)', 'grid' => 'Grid', 'split' => 'Two columns']]),
                    self::f('columns', 'range', 'Grid columns', 'أعمدة الشبكة', ['default' => 3, 'min' => 1, 'max' => 8, 'step' => 1, 'show_if' => ['layout' => 'grid']]),
                    self::f('gap', 'range', 'Gap', 'المسافة بين العناصر', ['default' => 16, 'min' => 0, 'max' => 120, 'step' => 4, 'unit' => 'px']),
                    self::f('align', 'select', 'Align items', 'محاذاة العناصر', ['default' => 'start', 'options' => ['start' => 'Start', 'center' => 'Center', 'end' => 'End', 'stretch' => 'Stretch']]),
                    self::f('min_height', 'range', 'Minimum height', 'أقل ارتفاع', ['default' => 0, 'min' => 0, 'max' => 100, 'step' => 5, 'unit' => 'vh']),
                ],
                'blocks' => $richBlocks,
                'max_blocks' => 40,
                'default_blocks' => [
                    ['type' => 'heading', 'settings' => ['text' => 'Make it yours', 'text_ar' => 'اصنعه بطريقتك', 'tag' => 'h2', 'size' => 48]],
                    ['type' => 'text', 'settings' => ['text' => 'Add, reorder and style blocks, then add your own CSS.', 'text_ar' => 'أضف الكتل ورتبها ونسّقها ثم أضف CSS الخاص بك.']],
                    ['type' => 'button', 'settings' => ['label' => 'Shop now', 'label_ar' => 'تسوق الآن', 'url' => '/shop', 'style' => 'primary']],
                ],
            ],
            'custom_html' => [
                'label' => 'Custom HTML', 'label_ar' => 'HTML مخصص', 'category' => 'advanced',
                'description' => 'Paste your own HTML (scripts and event handlers are removed for safety).',
                'fields' => [self::f('code', 'code', 'HTML', 'HTML', ['language' => 'html', 'default' => '<div class="my-block">Hello</div>'])],
            ],
            'spacer' => [
                'label' => 'Spacer / divider', 'label_ar' => 'مسافة / فاصل', 'category' => 'advanced',
                'description' => 'Empty space or a line between sections.',
                'fields' => [self::f('height', 'range', 'Height', 'الارتفاع', ['default' => 48, 'min' => 0, 'max' => 400, 'step' => 4, 'unit' => 'px']), self::f('mobile_height', 'range', 'Height on mobile', 'الارتفاع على الجوال', ['default' => 24, 'min' => 0, 'max' => 400, 'step' => 4, 'unit' => 'px']), self::f('divider', 'checkbox', 'Show a line', 'إظهار خط')],
                'style' => ['padding_top' => 0, 'padding_bottom' => 0],
            ],

            // ───────────── Footer group ─────────────
            'footer' => [
                'label' => 'Footer', 'label_ar' => 'تذييل الصفحة', 'category' => 'footer', 'group' => 'footer',
                'description' => 'Link columns, text, newsletter and social links.',
                'fields' => [
                    self::f('logo', 'image', 'Logo', 'الشعار'),
                    self::t('tagline', 'textarea', 'About text', 'نبذة'),
                    self::t('copyright', 'text', 'Copyright text (store name if empty)', 'نص حقوق النشر'),
                    self::f('show_language', 'checkbox', 'Language switch', 'تبديل اللغة', ['default' => true]),
                    self::f('show_payment_icons', 'checkbox', 'Payment methods', 'طرق الدفع', ['default' => true]),
                    self::f('show_back_to_top', 'checkbox', '"Back to top" link', 'العودة للأعلى', ['default' => true]),
                ],
                'blocks' => [
                    'link_list' => self::block('Link list', 'قائمة روابط', [self::t('heading', 'text', 'Heading', 'العنوان', 'Shop', 'المتجر'), self::t('links', 'links', 'Links — one per line: Label | /link', 'الروابط')]),
                    'text' => self::block('Text', 'نص', [self::t('heading', 'text', 'Heading', 'العنوان'), self::t('text', 'richtext', 'Text', 'النص')]),
                    'newsletter' => self::block('Newsletter', 'النشرة البريدية', [self::t('heading', 'text', 'Heading', 'العنوان', 'Newsletter', 'النشرة البريدية'), self::t('text', 'text', 'Text', 'النص')]),
                    'social' => self::block('Social links', 'روابط التواصل', [self::f('instagram', 'url', 'Instagram', 'إنستجرام'), self::f('tiktok', 'url', 'TikTok', 'تيك توك'), self::f('facebook', 'url', 'Facebook', 'فيسبوك'), self::f('x', 'url', 'X', 'X'), self::f('youtube', 'url', 'YouTube', 'يوتيوب'), self::f('whatsapp', 'url', 'WhatsApp link', 'واتساب')]),
                ],
                'max_blocks' => 8,
                'default_blocks' => [
                    ['type' => 'link_list', 'settings' => ['heading' => 'Shop', 'heading_ar' => 'المتجر', 'links' => 'All products | /shop', 'links_ar' => 'كل المنتجات | /shop']],
                    ['type' => 'link_list', 'settings' => ['heading' => 'Help', 'heading_ar' => 'المساعدة', 'links' => "Shipping | /pages/shipping\nReturns | /pages/returns\nTrack order | /track-order", 'links_ar' => "الشحن | /pages/shipping\nالاسترجاع | /pages/returns\nتتبع الطلب | /track-order"]],
                    ['type' => 'newsletter', 'settings' => ['heading' => 'Newsletter', 'heading_ar' => 'النشرة البريدية']],
                ],
                'style' => ['padding_top' => 64, 'padding_bottom' => 32],
            ],
        ];
    }
}
