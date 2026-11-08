<?php

namespace App\Ai\Chat;

use App\Ai\Llm\LlmClient;
use App\Core\Permissions\Permission;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Services\InventoryService;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Services\SetupChecklistService;

/**
 * The dashboard assistant. Code gathers every fact and number the asking user may see; a model
 * (when keys are configured) only phrases the answer. Without keys, or if every provider fails,
 * it answers from the same facts with built-in replies, so the chat always works.
 */
class StoreAssistant
{
    private const STEP_LINKS = [
        'verify_email' => '/email/verify',
        'add_products' => '/products/create',
        'publish' => '/settings',
    ];

    public function __construct(
        private readonly LlmClient $llm,
        private readonly InventoryService $inventory,
        private readonly SetupChecklistService $checklist,
    ) {}

    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $history
     * @return array{reply: string, actions: list<array<string, mixed>>, source: 'ai'|'local'}
     */
    public function answer(Store $store, User $user, string $message, array $history, string $locale): array
    {
        $facts = $this->facts($store, $user);
        $ar = $locale === 'ar';
        $intent = $this->intent($message);
        $actions = $this->actions($intent, $facts, $message, $user, $store, $ar);

        if ($this->llm->available()) {
            $reply = $this->llm->chat($this->systemPrompt($store, $user, $facts, $ar), [...$history, ['role' => 'user', 'content' => $message]]);
            if ($reply !== null) {
                return ['reply' => $reply, 'actions' => $actions, 'source' => 'ai'];
            }
        }

        return ['reply' => $this->localReply($intent, $facts, $user, $ar), 'actions' => $actions, 'source' => 'local'];
    }

    /** @return array<string, mixed> */
    public function facts(Store $store, User $user): array
    {
        $checklist = $this->checklist->for($store);

        $facts = [
            'store' => [
                'name' => $store->name,
                'status' => $store->status()->value,
                'url' => $store->url(),
                'sells' => $store->profileData()->sells,
            ],
            'setup' => [
                'done' => $checklist['done'],
                'total' => $checklist['total'],
                'next_step' => $checklist['next'],
                'remaining' => array_values(array_map(fn ($s) => $s['key'], array_filter($checklist['steps'], fn ($s) => ! $s['complete']))),
            ],
            // Orders, payments and analytics modules have not shipped yet.
            'sales_tracking' => false,
        ];

        if ($user->canIn($store, Permission::ProductsView)) {
            $summary = $this->inventory->summary();
            $facts['products'] = [
                'total' => $summary['total'],
                'live' => $summary['live'],
                'drafts' => $summary['drafts'],
                'running_low' => $summary['low_count'],
                'sold_out' => $summary['sold_out_count'],
                'lowest_stock' => array_map(fn ($p) => ['name' => $p['name'], 'stock' => $p['stock']], $summary['running_low']),
                'best_seller' => $summary['best_seller'] === null ? null : ['name' => $summary['best_seller']['name'] ?? '', 'sold' => $summary['best_seller']['sold']],
            ];
        }

        return $facts;
    }

    private function intent(string $message): string
    {
        $m = mb_strtolower(trim($message));

        return match (true) {
            (bool) preg_match('/^(please\s+)?add\s+|^(أضف|اضف|ضيف)\s+/u', $m) => 'add',
            (bool) preg_match('/restock|low|stock|sold out|run(ning)? out|inventory|مخزون|نفد|خلص|ناقص|قليل/u', $m) => 'stock',
            (bool) preg_match('/best|top|popular|selling most|الأكثر|اكثر|أفضل|افضل/u', $m) => 'best',
            (bool) preg_match('/sales|revenue|money|earn|orders?|profit|مبيعات|فلوس|ارباح|أرباح|طلبات|طلب/u', $m) => 'sales',
            (bool) preg_match('/publish|launch|live|go online|setup|set up|next|start|نشر|انشر|اطلق|أطلق|ابدأ|ابدا|تجهيز/u', $m) => 'setup',
            (bool) preg_match('/products?|catalog|items?|منتج|منتجات/u', $m) => 'products',
            (bool) preg_match('/^(hi|hello|hey|yo|good (morning|evening|afternoon))\b|مرحبا|اهلا|أهلا|السلام|صباح|مساء|ازيك|إزيك/u', $m) => 'greeting',
            (bool) preg_match('/thank|thanks|thx|شكرا|شكراً|تسلم/u', $m) => 'thanks',
            default => 'overview',
        };
    }

    /** @return list<array<string, mixed>> */
    private function actions(string $intent, array $facts, string $message, User $user, Store $store, bool $ar): array
    {
        $t = fn (string $en, string $arText) => $ar ? $arText : $en;
        $canManage = $user->canIn($store, Permission::ProductsManage);

        return match ($intent) {
            'add' => $canManage ? [['label' => $t('Create this draft', 'أنشئ المسودة'), 'post' => '/products', 'data' => ['note' => $message]]] : [],
            'stock' => isset($facts['products']) ? [['label' => $t('See running low', 'شوف اللي قرب يخلص'), 'href' => '/products?filter=low']] : [],
            'products', 'best' => isset($facts['products']) ? [['label' => $t('Open products', 'افتح المنتجات'), 'href' => '/products']] : [],
            'setup' => $this->stepAction($facts, $t),
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    private function stepAction(array $facts, callable $t): array
    {
        $next = $facts['setup']['next_step'];
        if ($next === null || ! isset(self::STEP_LINKS[$next])) {
            return [];
        }

        $labels = [
            'verify_email' => $t('Verify email', 'أكّد بريدك'),
            'add_products' => $t('Add a product', 'أضف منتج'),
            'publish' => $t('Go to publish', 'روح للنشر'),
        ];

        return [['label' => $labels[$next], 'href' => self::STEP_LINKS[$next]]];
    }

    private function systemPrompt(Store $store, User $user, array $facts, bool $ar): string
    {
        $language = $ar ? 'Egyptian Arabic' : 'English';
        $json = json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
        You are Minivora's AI team, talking with {$user->name}, who runs the online store "{$store->name}".
        Reply in {$language}, warm and direct, like a sharp business partner. Keep it under 120 words unless asked for detail.
        Only use the facts below for anything about this store. Never invent numbers, orders, customers or sales.
        Sales, orders and visitor tracking are not live yet ("sales_tracking": false): say so honestly if asked, and suggest what the owner can do now.
        You cannot take actions yourself; when something needs doing, say where in the dashboard to do it (Products, Settings, Publish).
        To add a product the owner can type "add" followed by the product, e.g. "add a leather bag for 1,250 pounds".
        If a question needs data you were not given, say you can't see that yet.

        Store facts (JSON):
        {$json}
        PROMPT;
    }

    private function localReply(string $intent, array $facts, User $user, bool $ar): string
    {
        $name = trim(explode(' ', trim($user->name))[0] ?? '');
        $p = $facts['products'] ?? null;
        $setup = $facts['setup'];
        $stepNames = $ar
            ? ['verify_email' => 'تأكيد بريدك', 'add_products' => 'إضافة منتجات', 'connect_payment' => 'ربط الدفع', 'set_shipping' => 'تحديد الشحن', 'publish' => 'نشر متجرك']
            : ['verify_email' => 'verifying your email', 'add_products' => 'adding products', 'connect_payment' => 'connecting payments', 'set_shipping' => 'setting shipping', 'publish' => 'publishing your store'];
        $next = $setup['next_step'] !== null ? ($stepNames[$setup['next_step']] ?? $setup['next_step']) : null;

        $setupLine = $next === null
            ? ($ar ? 'التجهيز خلص، متجرك جاهز.' : 'Setup is complete, your store is ready.')
            : ($ar ? "خلصت {$setup['done']} من {$setup['total']} خطوات تجهيز. الخطوة الجاية: {$next}." : "You're {$setup['done']} of {$setup['total']} setup steps in. Next up: {$next}.");

        $stockLine = function () use ($p, $ar): string {
            if ($p === null) {
                return $ar ? 'معنديش صلاحية أشوف المنتجات بحسابك.' : "Your role doesn't let me see products.";
            }
            if ($p['total'] === 0) {
                return $ar ? 'لسه مفيش منتجات. ابدأ بكتابة "أضف" واسم المنتج وسعره.' : 'No products yet. Type "add" with a product and price to create your first draft.';
            }
            if ($p['running_low'] === 0 && $p['sold_out'] === 0) {
                return $ar ? 'المخزون تمام، مفيش حاجة قربت تخلص.' : 'Stock looks healthy, nothing is running low.';
            }
            $names = implode(', ', array_map(fn ($i) => "{$i['name']} ({$i['stock']})", array_slice(array_filter($p['lowest_stock'], fn ($i) => $i['stock'] <= 5), 0, 3)));

            return $ar
                ? "عندك {$p['running_low']} منتج قرب يخلص و{$p['sold_out']} خلص خالص. ابدأ بـ: {$names}."
                : "{$p['running_low']} products are running low and {$p['sold_out']} sold out. I'd restock these first: {$names}.";
        };

        return match ($intent) {
            'greeting' => ($ar ? "أهلاً {$name}! " : "Hey {$name}! ").$setupLine.($ar ? ' اسألني عن المخزون أو المنتجات أو الخطوة الجاية.' : ' Ask me about stock, products or what to do next.'),
            'thanks' => $ar ? 'العفو! أنا هنا لو احتجت حاجة.' : "Anytime. I'm here when you need me.",
            'stock' => $stockLine(),
            'best' => $p === null ? $stockLine() : ($p['best_seller'] !== null
                ? ($ar ? "الأكثر مبيعاً: {$p['best_seller']['name']}، اتباع منه {$p['best_seller']['sold']}." : "Your best seller is {$p['best_seller']['name']}, with {$p['best_seller']['sold']} sold.")
                : ($ar ? 'لسه مفيش مبيعات أقدر أرتب بيها المنتجات.' : "No sales yet, so there's no best seller to show.")),
            'sales' => $ar
                ? 'تتبّع المبيعات والطلبات لسه بيتجهز، فمش هقدر أديك أرقام دلوقتي. '.$setupLine
                : "Sales and order tracking isn't live yet, so I can't give you numbers today. ".$setupLine,
            'setup' => $setupLine,
            'products' => $p === null ? $stockLine() : ($ar
                ? "عندك {$p['total']} منتج: {$p['live']} منشور و{$p['drafts']} مسودة."
                : "You have {$p['total']} products: {$p['live']} live and {$p['drafts']} in drafts."),
            'add' => $ar ? 'تمام، هعملها مسودة منتج. اضغط الزر تحت وراجع التفاصيل.' : "Got it. I'll turn that into a product draft — tap below and review the details.",
            default => ($ar ? 'ده الوضع النهارده: ' : "Here's where things stand: ").$setupLine.' '.($p !== null ? $stockLine() : ''),
        };
    }
}
