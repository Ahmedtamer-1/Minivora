<?php

namespace App\Ai\Understanding;

/**
 * Maps Arabic (including Egyptian dialect) and English owner questions to predefined
 * code-backed functions, period parameters, and data sources. Unmappable questions return
 * 3 localized suggestions instead of guessing. FR-AI-01, FR-AI-02, FR-AI-03, FR-AI-04
 */
class QuestionRouter
{
    /**
     * Ordered routing rules from most specific to most general.
     *
     * @var list<array{
     *     function: string,
     *     agent: string,
     *     source: string,
     *     is_financial: bool,
     *     default_period: string,
     *     pattern: string
     * }>
     */
    private const ROUTES = [
        [
            'function' => 'marketing.meta_verdict',
            'agent' => 'cmo',
            'source' => 'Minivora Meta ads & attribution',
            'is_financial' => true,
            'default_period' => 'last_7_days',
            'pattern' => '/\b(meta|facebook|fb|instagram|ig|roas|ad\s*spend|ads?|campaign|campaigns|verdict)\b|ميتا|فيسبوك|فيس\s*بوك|انستجرام|إنستجرام|انستا|إعلان|اعلان|إعلانات|اعلانات|حملة|حملات|عائد\s*الإعلان|التسويق/u',
        ],
        [
            'function' => 'fpa.budget_variance',
            'agent' => 'cfo',
            'source' => 'Minivora FP&A budgets & ledger',
            'is_financial' => true,
            'default_period' => 'this_month',
            'pattern' => '/\b(budget|variance|over\s*budget|under\s*budget|forecast\s*vs|planned\s*vs)\b|ميزانية|الميزانية|موازنة|الموازنة|انحراف|تجاوزنا|الفرق\s*عن\s*الميزانية|المخطط/u',
        ],
        [
            'function' => 'checkout.abandoned_checkouts',
            'agent' => 'cro',
            'source' => 'Minivora checkout sessions',
            'is_financial' => false,
            'default_period' => 'last_7_days',
            'pattern' => '/\b(abandon(ed)?|drop.?off|incomplete\s*(cart|checkout)|left\s*(in\s*)?(cart|checkout))\b|سلة|السلة|سلات|متروكة|سابوا\s*السلة|سابت\s*السلة|مكملوش|ماكملوش|تخلي\s*عن\s*الشراء|تشيك\s*اوت/u',
        ],
        [
            'function' => 'orders.refund_rate',
            'agent' => 'cxo',
            'source' => 'Minivora orders & refunds',
            'is_financial' => true,
            'default_period' => 'last_7_days',
            'pattern' => '/\b(refund|refunds|return\s*rate|returns|chargeback)\b|مرتجع|مرتجعات|المرتجعات|استرجاع|الاسترجاع|استرداد|رجّعوا|رجعوا|الطلبات\s*الراجعة/u',
        ],
        [
            'function' => 'finance.cash_balance',
            'agent' => 'cfo',
            'source' => 'Minivora ledger (cash & bank accounts)',
            'is_financial' => true,
            'default_period' => 'today',
            'pattern' => '/\b(cash(\s*balance|\s*on\s*hand|\s*available|\s*position)?|bank\s*balance|liquidity|how\s*much\s*cash)\b|كاش|الكاش|نقدية|النقدية|الخزنة|خزنة|رصيد\s*(البنك|الحساب|الخزنة|الكاش)|الفلوس\s*(المتاحة|اللي\s*معانا|في\s*البنك)/u',
        ],
        [
            'function' => 'finance.profit_and_loss',
            'agent' => 'cfo',
            'source' => 'Minivora ledger (posted journal entries)',
            'is_financial' => true,
            'default_period' => 'this_month',
            'pattern' => '/\b(profit|p&l|pnl|profit\s*and\s*loss|net\s*income|margin|earnings|expenses?\s*vs|bottom\s*line|break.?even)\b|ربح|أرباح|ارباح|الربح|الأرباح|مكسب|المكسب|كسبنا|كسبت|خسارة|صافي\s*(الربح|الدخل)|قائمة\s*الدخل|هامش/u',
        ],
        [
            'function' => 'catalog.low_stock',
            'agent' => 'coo',
            'source' => 'Minivora catalog inventory',
            'is_financial' => false,
            'default_period' => 'today',
            'pattern' => '/\b(low\s*stock|restock|out\s*of\s*stock|sold\s*out|reorder|running\s*low|inventory|stock\s*level)\b|مخزون|المخزون|قرب\s*يخلص|قربت\s*تخلص|خلصت|خلص|نفد|نفذ|ناقص|نواقص|إعادة\s*طلب|اعادة\s*طلب|كمية\s*قليلة/u',
        ],
        [
            'function' => 'analytics.top_products',
            'agent' => 'cro',
            'source' => 'Minivora orders & catalog',
            'is_financial' => false,
            'default_period' => 'this_month',
            'pattern' => '/\b(top\s*products?|best\s*sell(er|ers|ing)|most\s*popular|highest\s*selling|star\s*products?)\b|أكثر\s*مبيع|اكثر\s*مبيع|أكتر\s*(منتج|حاجة)\s*(بتتباع|بيتباع|مبيع)|أفضل\s*منتج|افضل\s*منتج|أعلى\s*منتج|المنتجات\s*الأكثر/u',
        ],
        [
            'function' => 'orders.today_summary',
            'agent' => 'coo',
            'source' => 'Minivora orders',
            'is_financial' => true,
            'default_period' => 'today',
            'pattern' => '/\b(orders?\s*(today|so\s*far|count|status|pending)|how\s*many\s*orders|today\'?s\s*orders|new\s*orders)\b|كام\s*(طلب|أوردر|اوردر)|كم\s*(طلب|أوردر|اوردر)|الطلبات\s*(النهاردة|النهارده|اليوم|الجديدة)|أوردرات|اوردرات|طلبات\s*(النهاردة|النهارده|اليوم)/u',
        ],
        [
            'function' => 'analytics.sales_summary',
            'agent' => 'cro',
            'source' => 'Minivora ledger & orders',
            'is_financial' => true,
            'default_period' => 'this_month',
            'pattern' => '/\b(sales|revenue|turnover|gmv|how\s*much\s*did\s*(we|i)\s*(sell|make)|total\s*sold)\b|مبيعات|المبيعات|إيرادات|ايرادات|الإيرادات|بعت\s*بكام|بعنا\s*بكام|بعنا\s*كام|بعت\s*كام|دخل\s*المتجر|حجم\s*المبيعات/u',
        ],
    ];

    /**
     * Route a natural-language question to a predefined function or return 3 localized suggestions.
     *
     * @return array<string, mixed>
     */
    public function route(string $question, ?string $locale = null): array
    {
        $trimmed = trim($question);
        $detectedLocale = $locale ?? $this->detectLocale($trimmed);
        $normalized = mb_strtolower($trimmed);

        foreach (self::ROUTES as $rule) {
            if (preg_match($rule['pattern'], $normalized) === 1) {
                $period = $this->extractPeriod($normalized, $rule['default_period']);

                $result = [
                    'matched' => true,
                    'function' => $rule['function'],
                    'agent' => $rule['agent'],
                    'params' => [
                        'period' => $period,
                    ],
                    'locale' => $detectedLocale,
                    'is_financial' => $rule['is_financial'],
                    'period' => $period,
                    'source' => $rule['source'],
                ];

                return $result;
            }
        }

        return [
            'matched' => false,
            'function' => null,
            'agent' => null,
            'params' => [],
            'locale' => $detectedLocale,
            'suggestions' => $this->suggestions($detectedLocale),
        ];
    }

    public function detectLocale(string $text): string
    {
        return preg_match('/\p{Arabic}/u', $text) === 1 ? 'ar' : 'en';
    }

    private function extractPeriod(string $normalized, string $default): string
    {
        return match (true) {
            preg_match('/\b(today|so\s*far\s*today)\b|النهاردة|النهارده|اليوم/u', $normalized) === 1 => 'today',
            preg_match('/\byesterday\b|امبارح|إمبارح|أمس|امس/u', $normalized) === 1 => 'yesterday',
            preg_match('/\b(last\s*7\s*days|this\s*week|last\s*week|past\s*week)\b|الأسبوع|الاسبوع|7\s*(أيام|ايام)|اخر\s*اسبوع|آخر\s*أسبوع/u', $normalized) === 1 => 'last_7_days',
            preg_match('/\blast\s*month\b|الشهر\s*(اللي\s*فات|الماضي|السابق)/u', $normalized) === 1 => 'last_month',
            preg_match('/\bthis\s*month\b|الشهر\s*(ده|دا|الحالي)/u', $normalized) === 1 => 'this_month',
            default => $default,
        };
    }

    /** @return list<string> */
    private function suggestions(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'مبيعات الشهر ده كام؟',
                'إيه المنتجات اللي قربت تخلص من المخزون؟',
                'صافي الربح والرصيد في الخزنة كام؟',
            ];
        }

        return [
            'How were my sales this month?',
            'Which products are running low on stock?',
            'What is my net profit and cash balance?',
        ];
    }
}
