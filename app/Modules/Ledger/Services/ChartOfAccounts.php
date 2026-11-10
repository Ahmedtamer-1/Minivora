<?php

namespace App\Modules\Ledger\Services;

use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Models\Account;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The default chart of accounts for a small Egyptian online retailer (FR-LED-01), and lookups by
 * code. Codes follow the usual numbering: 1 assets, 2 liabilities, 3 equity, 4 revenue,
 * 5 cost of sales and variable costs, 6 operating (fixed) costs.
 *
 * Subtypes drive the reports: cash/bank/clearing are "money" (cash flow statement), cogs and
 * variable feed contribution margin and break-even, fixed is the overhead F in BE = F / (1 - c/p),
 * contra accounts reduce their section (discounts, returns, drawings, depreciation).
 */
class ChartOfAccounts
{
    public const CASH = '1000';

    public const BANK = '1010';

    public const CLEARING = '1020';

    public const RECEIVABLE = '1100';

    public const INVENTORY = '1200';

    public const VAT_IN = '1300';

    public const EQUIPMENT = '1500';

    public const PAYABLE = '2000';

    public const CARD_PAYABLE = '2010';

    public const VAT_OUT = '2100';

    public const CAPITAL = '3000';

    public const DRAWINGS = '3100';

    public const RETAINED = '3200';

    public const OPENING = '3900';

    public const SALES = '4000';

    public const SHIPPING_INCOME = '4100';

    public const DISCOUNTS = '4900';

    public const RETURNS = '4910';

    public const COGS = '5000';

    public const FEES = '5100';

    public const SHIPPING = '5200';

    public const PACKAGING = '5300';

    public const MARKETING = '6000';

    /** Money accounts: the cash flow statement follows these. */
    public const MONEY_SUBTYPES = ['cash', 'bank', 'clearing'];

    /** @var list<array{0: string, 1: string, 2: string, 3: AccountType, 4: string}> code, English, Arabic, type, subtype */
    public const DEFAULTS = [
        [self::CASH, 'Cash on hand', 'النقدية بالخزينة', AccountType::Asset, 'cash'],
        [self::BANK, 'Bank account', 'الحساب البنكي', AccountType::Asset, 'bank'],
        [self::CLEARING, 'Payment gateway clearing', 'مستحقات بوابة الدفع', AccountType::Asset, 'clearing'],
        [self::RECEIVABLE, 'Accounts receivable', 'العملاء (مدينون)', AccountType::Asset, 'receivable'],
        [self::INVENTORY, 'Inventory', 'المخزون', AccountType::Asset, 'inventory'],
        [self::VAT_IN, 'VAT recoverable (input)', 'ضريبة القيمة المضافة على المشتريات', AccountType::Asset, 'tax'],
        ['1400', 'Prepaid expenses', 'مصروفات مدفوعة مقدماً', AccountType::Asset, 'prepaid'],
        [self::EQUIPMENT, 'Equipment', 'الأصول والمعدات', AccountType::Asset, 'fixed_asset'],
        ['1590', 'Accumulated depreciation', 'مجمع الإهلاك', AccountType::Asset, 'contra'],
        [self::PAYABLE, 'Accounts payable', 'الموردون (دائنون)', AccountType::Liability, 'payable'],
        [self::CARD_PAYABLE, 'Credit card payable', 'مستحقات بطاقة الائتمان', AccountType::Liability, 'payable'],
        [self::VAT_OUT, 'VAT payable (output)', 'ضريبة القيمة المضافة المستحقة', AccountType::Liability, 'tax'],
        ['2200', 'Accrued salaries', 'رواتب مستحقة', AccountType::Liability, 'accrued'],
        ['2300', 'Loans', 'قروض', AccountType::Liability, 'loan'],
        [self::CAPITAL, "Owner's capital", 'رأس المال', AccountType::Equity, 'capital'],
        [self::DRAWINGS, "Owner's drawings", 'مسحوبات المالك', AccountType::Equity, 'contra'],
        [self::RETAINED, 'Retained earnings', 'الأرباح المحتجزة', AccountType::Equity, 'retained'],
        [self::OPENING, 'Opening balance equity', 'أرصدة افتتاحية', AccountType::Equity, 'opening'],
        [self::SALES, 'Sales revenue', 'إيرادات المبيعات', AccountType::Revenue, 'sales'],
        [self::SHIPPING_INCOME, 'Shipping charged to customers', 'إيراد الشحن', AccountType::Revenue, 'sales'],
        ['4200', 'Other income', 'إيرادات أخرى', AccountType::Revenue, 'other'],
        [self::DISCOUNTS, 'Sales discounts', 'خصومات المبيعات', AccountType::Revenue, 'contra'],
        [self::RETURNS, 'Sales returns and refunds', 'مردودات ومرتجعات المبيعات', AccountType::Revenue, 'contra'],
        [self::COGS, 'Cost of goods sold', 'تكلفة البضاعة المباعة', AccountType::Expense, 'cogs'],
        [self::FEES, 'Payment fees', 'عمولات الدفع', AccountType::Expense, 'variable'],
        [self::SHIPPING, 'Shipping and courier', 'الشحن والتوصيل', AccountType::Expense, 'variable'],
        [self::PACKAGING, 'Packaging', 'التغليف', AccountType::Expense, 'variable'],
        [self::MARKETING, 'Marketing and ads', 'التسويق والإعلانات', AccountType::Expense, 'fixed'],
        ['6100', 'Rent', 'الإيجار', AccountType::Expense, 'fixed'],
        ['6200', 'Salaries and wages', 'الرواتب والأجور', AccountType::Expense, 'fixed'],
        ['6300', 'Utilities and internet', 'المرافق والإنترنت', AccountType::Expense, 'fixed'],
        ['6400', 'Software and subscriptions', 'البرامج والاشتراكات', AccountType::Expense, 'fixed'],
        ['6500', 'Bank charges', 'مصروفات بنكية', AccountType::Expense, 'fixed'],
        ['6600', 'Depreciation', 'الإهلاك', AccountType::Expense, 'fixed'],
        ['6700', 'Professional fees', 'أتعاب مهنية ومحاسبية', AccountType::Expense, 'fixed'],
        ['6900', 'Other expenses', 'مصروفات أخرى', AccountType::Expense, 'fixed'],
    ];

    /** @var array<int, Collection<string, Account>> store id => accounts by code */
    private array $cache = [];

    /** Create any missing default accounts for the current store. Safe to call often. */
    public function ensure(int $storeId): void
    {
        $existing = Account::query()->pluck('code')->all();
        foreach (self::DEFAULTS as [$code, $en, $ar, $type, $subtype]) {
            if (! in_array($code, $existing, true)) {
                Account::query()->create([
                    'code' => $code, 'name_en' => $en, 'name_ar' => $ar, 'type' => $type,
                    'subtype' => $subtype, 'is_system' => true,
                ]);
            }
        }
        unset($this->cache[$storeId]);
    }

    /** @return Collection<string, Account> */
    public function all(int $storeId): Collection
    {
        if (! isset($this->cache[$storeId])) {
            $accounts = Account::query()->orderBy('code')->get()->keyBy('code');
            if ($accounts->count() < count(self::DEFAULTS)) {
                $this->ensure($storeId);
                $accounts = Account::query()->orderBy('code')->get()->keyBy('code');
            }
            $this->cache[$storeId] = $accounts;
        }

        return $this->cache[$storeId];
    }

    public function get(int $storeId, string $code): Account
    {
        return $this->all($storeId)->get($code) ?? throw new InvalidArgumentException("No account {$code}.");
    }

    public function forget(int $storeId): void
    {
        unset($this->cache[$storeId]);
    }

    /** The money account an expense was paid from (or the payable it was charged to). */
    public static function codeForMethod(string $method): string
    {
        return match ($method) {
            'cash' => self::CASH,
            'bank' => self::BANK,
            'card' => self::CARD_PAYABLE,
            'payable' => self::PAYABLE,
            default => throw new InvalidArgumentException("Unknown payment method {$method}."),
        };
    }
}
