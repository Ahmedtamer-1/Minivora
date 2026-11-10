// Placeholder store drafts for the first-run flow. Until the Store Builder agent ships, the
// "building" step picks the closest preset to the owner's description and shows it as a preview.
import type { Locale } from '@/types';

export interface DraftProduct {
    name: string;
    price: number;
}

export interface Draft {
    hero: string;
    tagline: string;
    nav: string[];
    products: DraftProduct[];
    /** Short noun for the building checklist ("jewelry", "bags"…). */
    noun: string;
    /** Where shipping is set up for. */
    city: string;
}

type Preset = { match: RegExp; en: Draft; ar: Draft };

const presets: Preset[] = [
    {
        match: /jewel|ring|gold|silver|necklace|bracelet|مجوهر|اكسسوار|خاتم|ذهب|فض/i,
        en: {
            hero: 'Made in Egypt.',
            tagline: 'Pharaonic gold, rings and cuffs, delivered across Egypt.',
            nav: ['Shop', 'Rings', 'Cuffs', 'Bracelets'],
            products: [
                { name: 'Isis Wings Ring', price: 780 },
                { name: 'Turquoise Charm Bracelet', price: 650 },
                { name: 'Hammered Gold Cuff', price: 1150 },
                { name: 'Eye of Horus Ring', price: 720 },
            ],
            noun: 'jewelry',
            city: 'Cairo and Giza',
        },
        ar: {
            hero: 'صُنع في مصر.',
            tagline: 'ذهب فرعوني وخواتم وأساور، توصيل لكل مصر.',
            nav: ['المتجر', 'خواتم', 'أساور عريضة', 'أساور'],
            products: [
                { name: 'خاتم جناحي إيزيس', price: 780 },
                { name: 'سوار فيروز', price: 650 },
                { name: 'سوار ذهبي مطروق', price: 1150 },
                { name: 'خاتم عين حورس', price: 720 },
            ],
            noun: 'المجوهرات',
            city: 'القاهرة والجيزة',
        },
    },
    {
        match: /leather|bag|wallet|belt|tote|جلد|شنط|حقيب|محفظ/i,
        en: {
            hero: 'Carried for years.',
            tagline: 'Handmade leather bags and wallets, stitched in Alexandria.',
            nav: ['Shop', 'Bags', 'Wallets', 'Belts'],
            products: [
                { name: 'Classic Tote', price: 1450 },
                { name: 'Slim Card Wallet', price: 380 },
                { name: 'Crossbody Bag', price: 1100 },
                { name: 'Braided Belt', price: 420 },
            ],
            noun: 'bags',
            city: 'Alexandria',
        },
        ar: {
            hero: 'تعيش معك سنين.',
            tagline: 'شنط ومحافظ جلد مصنوعة يدويًا في الإسكندرية.',
            nav: ['المتجر', 'شنط', 'محافظ', 'أحزمة'],
            products: [
                { name: 'شنطة توت كلاسيك', price: 1450 },
                { name: 'محفظة كروت', price: 380 },
                { name: 'شنطة كروس', price: 1100 },
                { name: 'حزام مضفّر', price: 420 },
            ],
            noun: 'الشنط',
            city: 'الإسكندرية',
        },
    },
    {
        match: /coffee|roast|bean|espresso|tea|قهو|بن|شاي/i,
        en: {
            hero: 'Roasted this week.',
            tagline: 'Fresh beans and blends, ground the way you brew.',
            nav: ['Shop', 'Beans', 'Blends', 'Gear'],
            products: [
                { name: 'Ethiopian Yirgacheffe', price: 320 },
                { name: 'House Espresso', price: 280 },
                { name: 'Turkish Blend', price: 240 },
                { name: 'Pour-over Kit', price: 950 },
            ],
            noun: 'coffee',
            city: 'Cairo and Giza',
        },
        ar: {
            hero: 'محمّصة الأسبوع ده.',
            tagline: 'بن وخلطات طازة، مطحونة على طريقتك.',
            nav: ['المتجر', 'بن', 'خلطات', 'أدوات'],
            products: [
                { name: 'بن إثيوبي', price: 320 },
                { name: 'إسبريسو البيت', price: 280 },
                { name: 'خلطة تركي', price: 240 },
                { name: 'طقم تقطير', price: 950 },
            ],
            noun: 'القهوة',
            city: 'القاهرة والجيزة',
        },
    },
    {
        match: /kid|child|baby|toddler|أطفال|طفل|بيبي/i,
        en: {
            hero: 'Made for playing.',
            tagline: 'Soft cotton clothes for kids, from newborn to ten.',
            nav: ['Shop', 'Girls', 'Boys', 'Baby'],
            products: [
                { name: 'Cotton Romper', price: 290 },
                { name: 'Denim Overalls', price: 450 },
                { name: 'Striped Tee', price: 210 },
                { name: 'Knit Cardigan', price: 380 },
            ],
            noun: 'kids’ clothes',
            city: 'Giza',
        },
        ar: {
            hero: 'معمولة للعب.',
            tagline: 'هدوم قطن ناعمة للأطفال، من حديثي الولادة لعشر سنين.',
            nav: ['المتجر', 'بنات', 'أولاد', 'بيبي'],
            products: [
                { name: 'أوفرول قطن', price: 290 },
                { name: 'سالوبيت جينز', price: 450 },
                { name: 'تيشيرت مقلّم', price: 210 },
                { name: 'كارديجان تريكو', price: 380 },
            ],
            noun: 'هدوم الأطفال',
            city: 'الجيزة',
        },
    },
];

const fallback: Pick<Preset, 'en' | 'ar'> = {
    en: {
        hero: 'Made with care.',
        tagline: 'Our favourite things, delivered across Egypt.',
        nav: ['Shop', 'New in', 'Best sellers', 'About'],
        products: [
            { name: 'Signature piece', price: 450 },
            { name: 'Everyday classic', price: 320 },
            { name: 'Gift set', price: 780 },
            { name: 'Limited edition', price: 990 },
        ],
        noun: 'products',
        city: 'Cairo and Giza',
    },
    ar: {
        hero: 'معمول بحب.',
        tagline: 'حاجاتنا المفضلة، توصيل لكل مصر.',
        nav: ['المتجر', 'الجديد', 'الأكثر مبيعًا', 'عنّا'],
        products: [
            { name: 'القطعة المميزة', price: 450 },
            { name: 'كلاسيك يومي', price: 320 },
            { name: 'طقم هدية', price: 780 },
            { name: 'إصدار محدود', price: 990 },
        ],
        noun: 'المنتجات',
        city: 'القاهرة والجيزة',
    },
};

export function draftFor(description: string | null | undefined, locale: Locale): Draft {
    const preset = presets.find((p) => p.match.test(description ?? '')) ?? fallback;
    return preset[locale];
}

export interface Palette {
    key: string;
    /** Page, raised panel, card, image well. */
    bg: string;
    panel: string;
    card: string;
    image: string;
    ink: string;
    muted: string;
    accent: string;
    onAccent: string;
    /** Price colour. */
    price: string;
}

/** Neutral draft look while the store is still being built (before colours are picked). */
export const draftPalette: Palette = {
    key: 'draft',
    bg: '#ffffff',
    panel: '#eaf4f8',
    card: '#ffffff',
    image: '#e8eef3',
    ink: '#0f1b26',
    muted: '#53606c',
    accent: '#1f7aa3',
    onAccent: '#ffffff',
    price: '#0f1b26',
};

export const palettes: Palette[] = [
    { key: 'classic', bg: '#ffffff', panel: '#f3f4f6', card: '#ffffff', image: '#e9ebef', ink: '#111418', muted: '#5b6470', accent: '#111418', onAccent: '#ffffff', price: '#111418' },
    { key: 'sand', bg: '#faf6f0', panel: '#efe5d6', card: '#fffdf9', image: '#e7dac6', ink: '#2b2117', muted: '#6e5f4e', accent: '#8a5a2b', onAccent: '#ffffff', price: '#8a5a2b' },
    { key: 'emerald', bg: '#2e4a3d', panel: '#3a5a4b', card: '#365445', image: '#f3ecdf', ink: '#f6efe2', muted: '#c6d4c9', accent: '#c9a24a', onAccent: '#1f2d26', price: '#d9b45c' },
    { key: 'navy', bg: '#17213a', panel: '#22304f', card: '#1e2a45', image: '#ebe6da', ink: '#f3efe6', muted: '#b9c1d3', accent: '#c9a24a', onAccent: '#17213a', price: '#d9b45c' },
    { key: 'wine', bg: '#4a1a27', panel: '#5c2533', card: '#552130', image: '#f1e4dc', ink: '#f8ece6', muted: '#dcc3c3', accent: '#d4a855', onAccent: '#3a1520', price: '#e0b866' },
];

export function paletteFor(key: string | null | undefined): Palette {
    return palettes.find((p) => p.key === key) ?? palettes[2];
}
