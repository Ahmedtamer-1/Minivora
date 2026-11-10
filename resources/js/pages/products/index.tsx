// Products list (Figma "Products · HIG"): filters with counts, search, add tile, product cards.
import { Link, router, usePage } from '@inertiajs/react';
import { ImageIcon, Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { ButtonLink } from '@/components/ui/button';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { formatNumber, useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import { useCan } from '@/lib/permissions';
import type { Paginated, SharedProps } from '@/types';

type Filter = 'all' | 'live' | 'draft' | 'low' | 'sold_out';

export interface ProductCard {
    id: number;
    name: string;
    name_ar: string | null;
    price: number;
    stock: number;
    status: 'draft' | 'live';
    cover: string | null;
}

interface Props {
    products: Paginated<ProductCard>;
    filter: Filter;
    search: string;
    counts: Record<Filter, number>;
}

const filters: Filter[] = ['all', 'live', 'draft', 'low', 'sold_out'];

function StockNote({ stock }: { stock: number }) {
    const t = useT();
    if (stock === 0) return <span className="text-caption text-danger">{t('low.sold_out')}</span>;
    if (stock <= 5) return <span className="text-caption text-warning">{t('low.left', { count: stock })}</span>;
    return <span className="text-caption text-ink-muted">{t('low.in_stock', { count: stock })}</span>;
}

function Tile({ product }: { product: ProductCard }) {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    const can = useCan();
    const name = (locale === 'ar' ? product.name_ar || product.name : product.name || product.name_ar) || t('products.untitled');
    const body = (
        <>
            <div className="relative aspect-[5/4] overflow-hidden rounded-[20px] bg-accent-subtle">
                {product.cover ? (
                    <img src={product.cover} alt="" className="h-full w-full object-cover" loading="lazy" />
                ) : (
                    <div className="grid h-full w-full place-items-center">
                        <ImageIcon className="h-7 w-7 text-ink-muted" aria-hidden strokeWidth={1.5} />
                    </div>
                )}
                <Badge tone={product.status === 'live' ? 'success' : 'glass'} className="absolute start-3 top-3">
                    {t(`products.status_${product.status}`)}
                </Badge>
            </div>
            <div className="px-1 pb-1 pt-3">
                <p className="truncate text-sm font-semibold">{name}</p>
                <div className="mt-1 flex items-baseline justify-between gap-2">
                    <span className="tabular text-base">{formatPounds(product.price, locale)}</span>
                    <StockNote stock={product.stock} />
                </div>
            </div>
        </>
    );

    const cls = 'card block p-3 transition-transform hover:-translate-y-0.5 focus-visible:-translate-y-0.5';
    return can('products.manage') ? (
        <Link href={`/products/${product.id}/edit`} className={cls}>
            {body}
        </Link>
    ) : (
        <div className={cls}>{body}</div>
    );
}

export default function ProductsIndex({ products, filter, search, counts }: Props) {
    const t = useT();
    const can = useCan();
    const [q, setQ] = useState(search);
    const first = useRef(true);

    // Search as you type, without piling up history entries.
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => {
            router.get('/products', { filter: filter === 'all' ? undefined : filter, q: q || undefined }, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);
        return () => clearTimeout(timer);
    }, [q]); // eslint-disable-line react-hooks/exhaustive-deps

    const go = (f: Filter) => router.get('/products', { filter: f === 'all' ? undefined : f, q: q || undefined }, { preserveScroll: true });

    return (
        <DashboardLayout title={t('nav.products')}>
            <PageHeader
                title={t('products.title')}
                description={t('products.subtitle', { count: formatNumber(counts.all), live: formatNumber(counts.live) })}
                action={
                    <>
                        <label className="glass-strong flex h-11 w-full items-center gap-2 rounded-full px-4 shadow-sm sm:w-[300px]">
                            <Search className="h-4 w-4 shrink-0 text-ink-muted" aria-hidden />
                            <span className="sr-only">{t('products.search')}</span>
                            <input
                                type="search"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder={t('products.search')}
                                className="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-sm placeholder:text-ink-muted focus:ring-0"
                            />
                        </label>
                        {can('products.manage') ? (
                            <ButtonLink href="/products/create" size="lg" className="shadow-md">
                                <Plus className="h-[18px] w-[18px]" aria-hidden />
                                {t('products.add')}
                            </ButtonLink>
                        ) : null}
                    </>
                }
            />

            <div role="group" aria-label={t('products.filters')} className="-mx-1 mb-6 flex gap-2 overflow-x-auto px-1 pb-1">
                {filters.map((f) => {
                    const active = filter === f;
                    return (
                        <button
                            key={f}
                            type="button"
                            aria-pressed={active}
                            onClick={() => go(f)}
                            className={cn(
                                'inline-flex h-9 shrink-0 items-center gap-2 rounded-full px-4 text-sm shadow-sm transition-colors',
                                active ? 'bg-primary text-primary-fg' : 'glass-strong text-ink hover:bg-elevated',
                            )}
                        >
                            {t(`products.filter_${f}`)}
                            <span className={cn('tabular text-caption', active ? 'text-primary-fg' : 'text-ink-muted')}>{formatNumber(counts[f])}</span>
                        </button>
                    );
                })}
            </div>

            <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
                {can('products.manage') && products.current_page === 1 ? (
                    <Link
                        href="/products/create"
                        className="card col-span-2 flex min-h-[12rem] flex-col items-center justify-center gap-3 border-2 border-dashed !border-line-control p-6 text-center md:col-span-1"
                    >
                        <span className="grid h-14 w-14 place-items-center rounded-full bg-primary text-primary-fg shadow-md">
                            <Plus className="h-6 w-6" aria-hidden />
                        </span>
                        <span className="text-sm font-semibold">{t('products.add_tile')}</span>
                        <span className="text-caption text-ink-muted">{t('products.add_tile_hint')}</span>
                    </Link>
                ) : null}
                {products.data.map((p) => (
                    <Tile key={p.id} product={p} />
                ))}
            </div>

            {products.data.length === 0 ? (
                <p className="mt-8 text-center text-sm text-ink-muted">{search ? t('products.no_results', { q: search }) : t(`products.empty_${filter}`)}</p>
            ) : null}

            {products.last_page > 1 ? (
                <nav className="mt-8 flex items-center justify-between text-sm" aria-label={t('pagination.label')}>
                    {products.prev_page_url ? (
                        <Link href={products.prev_page_url} preserveScroll className="glass-strong rounded-full px-4 py-2 hover:bg-elevated">
                            {t('pagination.prev')}
                        </Link>
                    ) : (
                        <span />
                    )}
                    <span className="text-ink-muted">{t('pagination.page', { page: products.current_page, last: products.last_page })}</span>
                    {products.next_page_url ? (
                        <Link href={products.next_page_url} preserveScroll className="glass-strong rounded-full px-4 py-2 hover:bg-elevated">
                            {t('pagination.next')}
                        </Link>
                    ) : (
                        <span />
                    )}
                </nav>
            ) : null}
        </DashboardLayout>
    );
}
