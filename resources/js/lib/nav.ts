// One map of the dashboard, grouped the way the SRS groups the product (Documentation §5.13):
// run the day, sell, grow, build and money, then settings. The rail, the phone tab bar, the
// "More" sheet and the tabs inside each section all read from here, so they never drift apart.
import {
    BadgePercent,
    BarChart3,
    BookOpen,
    Boxes,
    FileBarChart,
    Files,
    House,
    Image,
    LayoutTemplate,
    ListChecks,
    Megaphone,
    Package,
    Palette,
    PiggyBank,
    Receipt,
    SearchCheck,
    ShoppingBag,
    ShoppingCart,
    SlidersHorizontal,
    Tags,
    Target,
    Undo2,
    Users,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import type { Permission } from './permissions';

export interface SubPage {
    key: string;
    href: string;
    icon: LucideIcon;
    permission?: Permission;
    /** Not built yet: the page is a preview with sample data. */
    planned?: boolean;
    /** Extra paths that belong to this tab (e.g. /products/12/edit). */
    match?: (path: string) => boolean;
}

export interface Section {
    key: string;
    icon: LucideIcon;
    href: string;
    /** Any one of these lets the member in. */
    permissions?: Permission[];
    planned?: boolean;
    pages?: SubPage[];
    match: (path: string) => boolean;
}

export type GroupKey = 'run' | 'sell' | 'grow' | 'build' | 'admin';

export interface Group {
    key: GroupKey;
    sections: Section[];
}

const under = (...prefixes: string[]) => (path: string) => prefixes.some((p) => path === p || path.startsWith(`${p}/`));

export const groups: Group[] = [
    {
        key: 'run',
        sections: [
            { key: 'home', icon: House, href: '/dashboard', match: (p) => p === '/dashboard' },
            { key: 'approvals', icon: ListChecks, href: '/approvals', permissions: ['ai.approve'], planned: true, match: under('/approvals') },
        ],
    },
    {
        key: 'sell',
        sections: [
            {
                key: 'products',
                icon: Package,
                href: '/products',
                permissions: ['products.view'],
                match: under('/products', '/categories', '/inventory'),
                pages: [
                    { key: 'products', href: '/products', icon: Package, match: under('/products') },
                    { key: 'categories', href: '/categories', icon: Tags, planned: true },
                    { key: 'inventory', href: '/inventory', icon: Boxes, planned: true },
                ],
            },
            {
                key: 'orders',
                icon: ShoppingBag,
                href: '/orders',
                permissions: ['orders.view'],
                match: under('/orders', '/returns'),
                pages: [
                    { key: 'orders', href: '/orders', icon: ShoppingBag, match: under('/orders') },
                    { key: 'returns', href: '/returns', icon: Undo2, permission: 'refunds.view', planned: true },
                ],
            },
            { key: 'customers', icon: Users, href: '/customers', permissions: ['orders.view'], match: under('/customers') },
        ],
    },
    {
        key: 'grow',
        sections: [
            { key: 'analytics', icon: BarChart3, href: '/analytics', permissions: ['orders.view', 'ledger.view'], planned: true, match: under('/analytics') },
            {
                key: 'marketing',
                icon: Megaphone,
                href: '/marketing',
                permissions: ['marketing.manage'],
                planned: true,
                match: under('/marketing'),
                pages: [
                    { key: 'overview', href: '/marketing', icon: Megaphone, match: (p) => p === '/marketing' },
                    { key: 'campaigns', href: '/marketing/campaigns', icon: Target },
                    { key: 'discounts', href: '/marketing/discounts', icon: BadgePercent },
                    { key: 'abandoned', href: '/marketing/abandoned', icon: ShoppingCart },
                ],
            },
            { key: 'seo', icon: SearchCheck, href: '/seo', permissions: ['marketing.manage', 'design.manage'], planned: true, match: under('/seo') },
        ],
    },
    {
        key: 'build',
        sections: [
            {
                key: 'builder',
                icon: LayoutTemplate,
                href: '/builder',
                permissions: ['design.manage'],
                planned: true,
                match: under('/builder'),
                pages: [
                    { key: 'pages', href: '/builder', icon: Files, match: (p) => p === '/builder' },
                    { key: 'theme', href: '/builder/theme', icon: Palette },
                    { key: 'media', href: '/builder/media', icon: Image },
                ],
            },
            {
                key: 'finance',
                icon: Wallet,
                href: '/finance',
                permissions: ['ledger.view', 'budgets.view'],
                planned: true,
                match: under('/finance'),
                pages: [
                    { key: 'overview', href: '/finance', icon: Wallet, match: (p) => p === '/finance' },
                    { key: 'reports', href: '/finance/reports', icon: FileBarChart, permission: 'ledger.view' },
                    { key: 'expenses', href: '/finance/expenses', icon: Receipt, permission: 'ledger.view' },
                    { key: 'budgets', href: '/finance/budgets', icon: PiggyBank, permission: 'budgets.view' },
                    { key: 'ledger', href: '/finance/ledger', icon: BookOpen, permission: 'ledger.view' },
                ],
            },
        ],
    },
    {
        key: 'admin',
        // Everyone has Settings: members without store settings land on their own account.
        sections: [{ key: 'settings', icon: SlidersHorizontal, href: '/settings', match: under('/settings') }],
    },
];

export const allSections: Section[] = groups.flatMap((g) => g.sections);

export function sectionFor(path: string): Section | undefined {
    return allSections.find((s) => s.match(path));
}

export function pageFor(section: Section, path: string): SubPage | undefined {
    return section.pages?.find((p) => (p.match ? p.match(path) : path === p.href));
}

export function cleanPath(url: string): string {
    return url.split(/[?#]/)[0].replace(/\/$/, '') || '/';
}
