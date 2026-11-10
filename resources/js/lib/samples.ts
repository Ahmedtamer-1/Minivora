// Sample data for preview pages (areas not built yet). Shown under a "Sample" chip, never mixed
// with real store data. Replace each block with page props when its module ships.
import { useLocale } from './i18n';

export interface Bi {
    en: string;
    ar: string;
}

/** Pick the dashboard language's side of a bilingual sample string. */
export function useBi(): (text: Bi) => string {
    const locale = useLocale();
    return (text) => text[locale];
}

const b = (en: string, ar: string): Bi => ({ en, ar });

export const sampleApprovals = [
    {
        id: 1,
        agent: 'cmo',
        title: b('Pause the "Summer bags" ad set', 'إيقاف مجموعة إعلانات «شنط الصيف»'),
        why: b('Spent 1,840 EGP in 7 days with 2 sales; cost per sale is 3.1× your target.', 'صرفت 1,840 ج.م في 7 أيام مقابل بيعتين؛ تكلفة البيعة 3.1 ضعف هدفك.'),
        impact: 184000,
        risk: 'act',
        age: b('12 min ago', 'منذ 12 دقيقة'),
    },
    {
        id: 2,
        agent: 'coo',
        title: b('Reorder 40 × Leather tote (brown)', 'إعادة طلب 40 × شنطة جلد (بني)'),
        why: b('Sells out in about 6 days at the current pace. Supplier lead time is 9 days.', 'هتخلص خلال 6 أيام تقريبًا بالمعدل الحالي، والمورد بيحتاج 9 أيام.'),
        impact: 520000,
        risk: 'act',
        age: b('1 h ago', 'منذ ساعة'),
    },
    {
        id: 3,
        agent: 'cto',
        title: b('Add alt text to 14 product images', 'إضافة نص بديل لـ 14 صورة منتج'),
        why: b('Images without alt text hurt search ranking and screen-reader shoppers.', 'الصور من غير نص بديل بتضر ترتيب البحث والمتسوقين اللي بيستخدموا قارئ الشاشة.'),
        impact: 0,
        risk: 'draft',
        age: b('Yesterday', 'أمس'),
    },
];

export const sampleCategories = [
    { id: 1, name: b('Bags', 'شنط'), products: 18, visible: true },
    { id: 2, name: b('Wallets', 'محافظ'), products: 11, visible: true },
    { id: 3, name: b('Belts', 'أحزمة'), products: 6, visible: true },
    { id: 4, name: b('Gift sets', 'هدايا'), products: 3, visible: false },
];

export const sampleStock = [
    { id: 1, name: b('Leather tote · Brown', 'شنطة جلد · بني'), sku: 'TOTE-BR', stock: 4, daily: 0.7, reorder: 10 },
    { id: 2, name: b('Card wallet · Black', 'محفظة كروت · أسود'), sku: 'WAL-BK', stock: 9, daily: 0.9, reorder: 8 },
    { id: 3, name: b('Crossbody · Tan', 'كروس · جملي'), sku: 'CROSS-TN', stock: 22, daily: 0.6, reorder: 8 },
    { id: 4, name: b('Belt · Classic', 'حزام · كلاسيك'), sku: 'BELT-CL', stock: 37, daily: 0.3, reorder: 6 },
];

export const sampleReturns = [
    { id: 1, order: '#1042', customer: b('Mona Adel', 'منى عادل'), reason: b('Wrong size', 'مقاس غلط'), amount: 65000, status: 'requested' },
    { id: 2, order: '#1031', customer: b('Karim Fathy', 'كريم فتحي'), reason: b('Arrived damaged', 'وصل تالف'), amount: 120000, status: 'received' },
    { id: 3, order: '#1019', customer: b('Salma Hany', 'سلمى هاني'), reason: b('Changed mind', 'غيرت رأيي'), amount: 45000, status: 'refunded' },
];

export const sampleCampaigns = [
    { id: 1, name: b('Summer bags · Instagram', 'شنط الصيف · إنستجرام'), spend: 184000, sales: 2, roas: 0.7, verdict: 'pause' },
    { id: 2, name: b('Wallets · Retargeting', 'محافظ · إعادة استهداف'), spend: 62000, sales: 9, roas: 4.1, verdict: 'scale' },
    { id: 3, name: b('Brand · Cairo 25–40', 'البراند · القاهرة 25–40'), spend: 95000, sales: 5, roas: 1.8, verdict: 'keep' },
];

export const sampleDiscounts = [
    { id: 1, code: 'WELCOME10', type: b('10% off first order', 'خصم 10% على أول طلب'), used: 38, status: 'active' },
    { id: 2, code: 'EID150', type: b('150 EGP off over 1,000', 'خصم 150 ج.م فوق 1,000'), used: 12, status: 'scheduled' },
    { id: 3, code: 'FREESHIP', type: b('Free shipping in Cairo', 'شحن مجاني داخل القاهرة'), used: 71, status: 'ended' },
];

export const sampleAbandoned = [
    { id: 1, customer: b('Nour Samir', 'نور سمير'), items: 2, value: 135000, step: b('Shipping', 'الشحن'), age: b('2 h ago', 'منذ ساعتين') },
    { id: 2, customer: b('Guest · 010…482', 'زائر · 010…482'), items: 1, value: 65000, step: b('Payment', 'الدفع'), age: b('5 h ago', 'منذ 5 ساعات') },
    { id: 3, customer: b('Omar Khaled', 'عمر خالد'), items: 3, value: 210000, step: b('Contact details', 'بيانات التواصل'), age: b('Yesterday', 'أمس') },
];

export const sampleSeoIssues = [
    { id: 1, severity: 'danger', text: b('3 products have no description', '3 منتجات من غير وصف'), fix: b('Draft with AI', 'اكتبها بالذكاء الاصطناعي') },
    { id: 2, severity: 'warning', text: b('14 images are missing alt text', '14 صورة من غير نص بديل'), fix: b('Add alt text', 'أضف نص بديل') },
    { id: 3, severity: 'warning', text: b('2 page titles are longer than 60 characters', 'عنوانين صفحات أطول من 60 حرف'), fix: b('Shorten', 'اختصر') },
    { id: 4, severity: 'success', text: b('Sitemap is up to date and submitted', 'خريطة الموقع محدثة ومرسلة'), fix: null },
];

export const samplePages = [
    { id: 1, name: b('Home', 'الرئيسية'), sections: 7, status: 'published', updated: b('Today', 'اليوم') },
    { id: 2, name: b('About us', 'من نحن'), sections: 3, status: 'published', updated: b('3 days ago', 'منذ 3 أيام') },
    { id: 3, name: b('Eid collection', 'مجموعة العيد'), sections: 5, status: 'draft', updated: b('Yesterday', 'أمس') },
    { id: 4, name: b('Shipping & returns', 'الشحن والاسترجاع'), sections: 2, status: 'published', updated: b('2 weeks ago', 'منذ أسبوعين') },
];

export const sampleExpenses = [
    { id: 1, date: '2026-11-08', what: b('Meta ads', 'إعلانات ميتا'), account: b('Marketing', 'تسويق'), amount: 95000 },
    { id: 2, date: '2026-11-05', what: b('Packaging boxes', 'علب تغليف'), account: b('Packaging', 'تغليف'), amount: 42000 },
    { id: 3, date: '2026-11-01', what: b('Workshop rent', 'إيجار الورشة'), account: b('Rent', 'إيجار'), amount: 600000 },
    { id: 4, date: '2026-11-01', what: b('Courier invoice', 'فاتورة الشحن'), account: b('Shipping', 'شحن'), amount: 128000 },
];

export const sampleBudgets = [
    { id: 1, account: b('Marketing', 'تسويق'), budget: 400000, actual: 318000 },
    { id: 2, account: b('Packaging', 'تغليف'), budget: 120000, actual: 131000 },
    { id: 3, account: b('Shipping', 'شحن'), budget: 300000, actual: 214000 },
    { id: 4, account: b('Rent', 'إيجار'), budget: 600000, actual: 600000 },
];

export const sampleJournal = [
    { id: 1, date: '2026-11-08', memo: b('Order #1048 paid (COD)', 'الطلب #1048 اتدفع (عند الاستلام)'), debit: b('Cash', 'النقدية'), credit: b('Sales', 'المبيعات'), amount: 135000 },
    { id: 2, date: '2026-11-08', memo: b('Order #1048 shipped: cost of goods', 'الطلب #1048 اتشحن: تكلفة البضاعة'), debit: b('Cost of goods sold', 'تكلفة المبيعات'), credit: b('Inventory', 'المخزون'), amount: 54000 },
    { id: 3, date: '2026-11-07', memo: b('Meta ad spend', 'مصروف إعلانات ميتا'), debit: b('Marketing expense', 'مصروف تسويق'), credit: b('Bank', 'البنك'), amount: 95000 },
];

export const sampleTopProducts = [
    { id: 1, name: b('Leather tote · Brown', 'شنطة جلد · بني'), units: 46, revenue: 2990000, share: 100 },
    { id: 2, name: b('Card wallet · Black', 'محفظة كروت · أسود'), units: 71, revenue: 1775000, share: 59 },
    { id: 3, name: b('Crossbody · Tan', 'كروس · جملي'), units: 22, revenue: 1210000, share: 40 },
    { id: 4, name: b('Belt · Classic', 'حزام · كلاسيك'), units: 30, revenue: 720000, share: 24 },
];

/** Daily revenue for the last 14 days, in pounds (for chart previews). */
export const sampleDaily = [3.2, 4.1, 2.8, 5.6, 4.9, 6.2, 7.4, 5.1, 4.4, 6.8, 8.1, 7.2, 6.4, 9.0];

export const sampleShippingZones = [
    { id: 1, name: b('Cairo & Giza', 'القاهرة والجيزة'), fee: 5000, days: b('1–2 days', '1–2 يوم'), cod: true },
    { id: 2, name: b('Alexandria & Delta', 'الإسكندرية والدلتا'), fee: 6500, days: b('2–3 days', '2–3 أيام'), cod: true },
    { id: 3, name: b('Upper Egypt', 'الصعيد'), fee: 8500, days: b('3–5 days', '3–5 أيام'), cod: false },
];
