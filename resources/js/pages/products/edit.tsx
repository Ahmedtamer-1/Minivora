// Add product, step 2 / edit (Figma "Add product · Step 2 · HIG"): photos, names, price and stock,
// colors, optional details, and a live preview of how shoppers will see it.
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Check, ChevronLeft, ChevronRight, ImageIcon, Plus, Sparkles, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { Badge, Label } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Field, Input, Textarea } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';
import { formatPounds, piastersToInput } from '@/lib/money';
import type { SharedProps } from '@/types';

interface Color {
    name: string;
    hex: string;
}

interface Product {
    id: number;
    name: string;
    name_ar: string | null;
    description: string | null;
    category: string | null;
    price: number;
    compare_at_price: number | null;
    cost: number | null;
    stock: number;
    status: 'draft' | 'live';
    has_options: boolean;
    colors: Color[];
    images: { path: string; url: string }[];
    seo_title: string | null;
    seo_description: string | null;
    source: 'manual' | 'note' | 'photos' | 'csv';
}

interface Props {
    product: Product;
    maxPhotos: number;
    categories: string[];
}

type Intent = 'save' | 'publish' | 'unpublish';

/** Pounds string ("2,150" / "99.5") to piasters for the preview; null if unreadable. */
function toPiasters(value: string): number | null {
    const clean = value.replace(/[,\s]/g, '');
    if (!/^\d+(\.\d{1,2})?$/.test(clean)) return null;
    const [whole, frac = ''] = clean.split('.');
    return Number(whole) * 100 + Number(frac.padEnd(2, '0'));
}

function Toggle({ checked, onChange, labelledBy }: { checked: boolean; onChange: (v: boolean) => void; labelledBy: string }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-labelledby={labelledBy}
            onClick={() => onChange(!checked)}
            className={cn('relative inline-flex h-7 w-11 shrink-0 items-center rounded-full transition-colors', checked ? 'bg-primary' : 'bg-chart-track')}
        >
            <span className={cn('inline-block h-6 w-6 rounded-full bg-white shadow-md transition-transform', checked ? 'translate-x-[18px] rtl:-translate-x-[18px]' : 'translate-x-0.5 rtl:-translate-x-0.5')} />
        </button>
    );
}

function DetailRow({ id, title, summary, children, open, onToggle }: { id: string; title: string; summary: string; children: ReactNode; open: boolean; onToggle: () => void }) {
    return (
        <div className="border-b border-line">
            <button type="button" onClick={onToggle} aria-expanded={open} aria-controls={`${id}-panel`} className="flex w-full items-center gap-3 py-3.5 text-start">
                <span className="min-w-0 flex-1">
                    <span className="block text-sm">{title}</span>
                    <span className="mt-0.5 block truncate text-caption text-ink-muted">{summary}</span>
                </span>
                <ChevronRight className={cn('h-4 w-4 shrink-0 text-ink-muted transition-transform rtl:-scale-x-100', open && 'rotate-90 rtl:-rotate-90')} aria-hidden />
            </button>
            {open ? (
                <div id={`${id}-panel`} className="space-y-4 pb-5">
                    {children}
                </div>
            ) : null}
        </div>
    );
}

export default function EditProduct({ product, maxPhotos, categories }: Props) {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    const photoInput = useRef<HTMLInputElement>(null);
    const nameInput = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, errors, transform } = useForm({
        name: product.name,
        name_ar: product.name_ar ?? '',
        price: piastersToInput(product.price),
        stock: String(product.stock),
        has_options: product.has_options,
        colors: product.colors,
        description: product.description ?? '',
        category: product.category ?? '',
        seo_title: product.seo_title ?? '',
        seo_description: product.seo_description ?? '',
        compare_at_price: piastersToInput(product.compare_at_price),
        cost: piastersToInput(product.cost),
        keep_images: product.images.map((i) => i.path),
        photos: [] as File[],
        intent: 'save' as Intent,
    });

    const [editingAr, setEditingAr] = useState(product.name_ar === null);
    const [open, setOpen] = useState<string | null>(null);
    const [previewLang, setPreviewLang] = useState<'en' | 'ar'>(locale);
    const [adding, setAdding] = useState(false);
    const [newColor, setNewColor] = useState<Color>({ name: '', hex: '#8B5A2B' });
    const [confirmDelete, setConfirmDelete] = useState(false);

    // Keep the server's order of saved photos; new ones get object URLs for previews.
    const savedByPath = useMemo(() => new Map(product.images.map((i) => [i.path, i.url])), [product.images]);
    const newUrls = useMemo(() => data.photos.map((f) => URL.createObjectURL(f)), [data.photos]);
    useEffect(() => () => newUrls.forEach((u) => URL.revokeObjectURL(u)), [newUrls]);
    const photos = [
        ...data.keep_images.map((p) => ({ key: p, url: savedByPath.get(p) ?? '', saved: true, index: -1 })),
        ...newUrls.map((u, i) => ({ key: `new-${i}`, url: u, saved: false, index: i })),
    ];

    const submit = (intent: Intent) => (e?: FormEvent) => {
        e?.preventDefault();
        // Files need multipart, which needs POST + _method spoofing.
        transform((d) => ({ ...d, intent, stock: Number(d.stock || 0), _method: 'put' }));
        post(`/products/${product.id}`, {
            forceFormData: data.photos.length > 0,
            preserveScroll: true,
            onSuccess: () => setData('photos', []),
        });
    };

    const addPhotos = (files: FileList | null) => {
        const list = Array.from(files ?? []).filter((f) => f.type.startsWith('image/'));
        setData('photos', [...data.photos, ...list].slice(0, Math.max(0, maxPhotos - data.keep_images.length)));
    };
    const removePhoto = (p: (typeof photos)[number]) => {
        if (p.saved) setData('keep_images', data.keep_images.filter((k) => k !== p.key));
        else setData('photos', data.photos.filter((_, i) => i !== p.index));
    };
    const makeCover = (path: string) => setData('keep_images', [path, ...data.keep_images.filter((k) => k !== path)]);

    const addColor = () => {
        if (!newColor.name.trim()) return;
        setData('colors', [...data.colors, { name: newColor.name.trim(), hex: newColor.hex }]);
        setNewColor({ name: '', hex: '#8B5A2B' });
        setAdding(false);
    };

    const price = toPiasters(data.price);
    const previewName = (previewLang === 'ar' ? data.name_ar || data.name : data.name || data.name_ar) || t('products.untitled');
    const cover = photos[0]?.url;
    const isLive = product.status === 'live';
    const allErrors = errors as Record<string, string>;

    return (
        <DashboardLayout title={data.name || t('edit.new')}>
            <form onSubmit={submit('save')}>
                <PageHeader
                    eyebrow={
                        <Link href="/products" className="mb-2 inline-flex items-center gap-1 text-sm text-ink-muted hover:text-ink">
                            <ChevronLeft className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
                            {t('nav.products')}
                        </Link>
                    }
                    title={
                        <span className="flex flex-wrap items-center gap-4">
                            <span className="min-w-0 break-words">{product.name || t('edit.new')}</span>
                            <Label className={isLive ? 'text-success' : 'text-ink-muted'}>{t(`products.status_${product.status}`)}</Label>
                        </span>
                    }
                    action={
                        isLive ? (
                            <>
                                <Button variant="glass" size="md" onClick={submit('unpublish')} disabled={processing}>
                                    {t('edit.to_drafts')}
                                </Button>
                                <Button type="submit" size="md" disabled={processing}>
                                    <Check className="h-4 w-4" aria-hidden />
                                    {t('edit.save_changes')}
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button type="submit" variant="glass" size="md" disabled={processing}>
                                    {t('edit.save_draft')}
                                </Button>
                                <Button size="md" onClick={submit('publish')} disabled={processing}>
                                    <Check className="h-4 w-4" aria-hidden />
                                    {t('edit.publish')}
                                </Button>
                            </>
                        )
                    }
                />

                {allErrors.publish || allErrors.photos ? (
                    <p role="alert" className="mb-4 rounded-full bg-danger-subtle px-4 py-2.5 text-sm text-danger">
                        {allErrors.publish ?? allErrors.photos}
                    </p>
                ) : null}

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,760fr)_minmax(0,496fr)]">
                    <Card className="p-6 sm:p-8">
                        {/* Photos */}
                        <fieldset>
                            <legend className="text-caption">{t('edit.photos')}</legend>
                            <div className="mt-3 flex flex-wrap gap-3">
                                {photos.map((p, i) => (
                                    <div key={p.key} className="group relative h-[110px] w-[110px] overflow-hidden rounded-[20px] bg-accent-subtle">
                                        <img src={p.url} alt={t('edit.photo_n', { n: i + 1 })} className="h-full w-full object-cover" />
                                        {i === 0 ? <Badge tone="glass" className="absolute start-2 top-2">{t('edit.cover')}</Badge> : null}
                                        <div className="absolute inset-x-2 bottom-2 flex justify-end gap-1 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100">
                                            {i > 0 && p.saved ? (
                                                <button type="button" onClick={() => makeCover(p.key)} className="glass-strong rounded-full px-2 py-1 text-[0.6875rem] font-medium">
                                                    {t('edit.make_cover')}
                                                </button>
                                            ) : null}
                                            <button type="button" onClick={() => removePhoto(p)} className="glass-strong grid h-7 w-7 place-items-center rounded-full" aria-label={t('edit.remove_photo', { n: i + 1 })}>
                                                <X className="h-3.5 w-3.5" aria-hidden />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                                {photos.length === 0
                                    ? [0, 1, 2].map((i) => (
                                          <div key={i} className="grid h-[110px] w-[110px] place-items-center rounded-[20px] bg-accent-subtle" aria-hidden>
                                              <ImageIcon className="h-6 w-6 text-ink-muted" strokeWidth={1.5} />
                                          </div>
                                      ))
                                    : null}
                                {photos.length < maxPhotos ? (
                                    <button
                                        type="button"
                                        onClick={() => photoInput.current?.click()}
                                        className="flex h-[110px] w-[110px] flex-col items-center justify-center gap-1 rounded-[20px] border-2 border-dashed border-line-control text-caption text-ink-muted hover:bg-secondary"
                                    >
                                        <Plus className="h-5 w-5" aria-hidden />
                                        {t('edit.add_photo')}
                                    </button>
                                ) : null}
                                <input ref={photoInput} type="file" accept="image/*" multiple className="sr-only" tabIndex={-1} aria-hidden onChange={(e) => addPhotos(e.target.files)} />
                            </div>
                        </fieldset>

                        {/* Names */}
                        <div className="mt-7">
                            <Field label={t('edit.name')} htmlFor="name" error={errors.name}>
                                <Input ref={nameInput} id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} maxLength={255} invalid={!!errors.name} />
                            </Field>
                            {editingAr ? (
                                <Field label={t('edit.name_ar')} htmlFor="name_ar" error={errors.name_ar} className="mt-4">
                                    <Input id="name_ar" dir="rtl" lang="ar" value={data.name_ar} onChange={(e) => setData('name_ar', e.target.value)} maxLength={255} />
                                </Field>
                            ) : (
                                <p className="mt-3 flex flex-wrap items-center gap-2 text-sm text-ink-muted">
                                    <span className="orb h-3.5 w-3.5 rounded-full" aria-hidden />
                                    {t('edit.name_ar')}:{' '}
                                    <span lang="ar" dir="rtl" className="text-ink">
                                        {data.name_ar}
                                    </span>
                                    <button type="button" onClick={() => setEditingAr(true)} className="text-caption text-ink-link hover:underline">
                                        {t('edit.edit')}
                                    </button>
                                </p>
                            )}
                        </div>

                        {/* Price and stock */}
                        <div className="mt-6 grid gap-4 sm:grid-cols-2">
                            <Field label={t('edit.price')} htmlFor="price" error={errors.price}>
                                <Input id="price" inputMode="decimal" value={data.price} onChange={(e) => setData('price', e.target.value)} placeholder="0" invalid={!!errors.price} dir="ltr" />
                            </Field>
                            <Field label={t('edit.stock')} htmlFor="stock" error={errors.stock}>
                                <Input id="stock" type="number" min={0} inputMode="numeric" value={data.stock} onChange={(e) => setData('stock', e.target.value)} invalid={!!errors.stock} dir="ltr" />
                            </Field>
                        </div>
                        {price !== null && price > 0 ? (
                            <p className="mt-3 flex items-center gap-2 text-caption text-ink-muted">
                                <Sparkles className="h-4 w-4 text-brand-accent" aria-hidden />
                                {t('edit.price_hint', { price: formatPounds(price, locale) })}
                            </p>
                        ) : null}

                        {/* Colors */}
                        <div className="mt-6 rounded-2xl bg-elevated p-4 shadow-sm">
                            <div className="flex items-center justify-between gap-3">
                                <span id="options-label" className="text-sm">
                                    {t('edit.options')}
                                </span>
                                <Toggle checked={data.has_options} onChange={(v) => setData('has_options', v)} labelledBy="options-label" />
                            </div>
                            {data.has_options ? (
                                <div className="mt-4 flex flex-wrap items-center gap-2">
                                    <span className="text-caption text-ink-muted">{t('edit.colors')}</span>
                                    {data.colors.map((c, i) => (
                                        <span key={`${c.name}-${i}`} className="inline-flex h-8 items-center gap-2 rounded-full bg-secondary pe-1 ps-3 text-caption">
                                            <span className="h-3 w-3 rounded-full ring-1 ring-black/10" style={{ background: c.hex }} aria-hidden />
                                            {c.name}
                                            <button type="button" onClick={() => setData('colors', data.colors.filter((_, j) => j !== i))} className="grid h-6 w-6 place-items-center rounded-full hover:bg-elevated" aria-label={t('edit.remove_color', { name: c.name })}>
                                                <X className="h-3 w-3" aria-hidden />
                                            </button>
                                        </span>
                                    ))}
                                    {adding ? (
                                        <span className="inline-flex items-center gap-2">
                                            <label className="sr-only" htmlFor="color-name">
                                                {t('edit.color_name')}
                                            </label>
                                            <input
                                                id="color-name"
                                                autoFocus
                                                value={newColor.name}
                                                onChange={(e) => setNewColor({ ...newColor, name: e.target.value })}
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        e.preventDefault();
                                                        addColor();
                                                    }
                                                    if (e.key === 'Escape') setAdding(false);
                                                }}
                                                placeholder={t('edit.color_name')}
                                                className="h-8 w-32 rounded-full border-line-control bg-elevated px-3 text-caption focus:border-line-focus focus:ring-1 focus:ring-line-focus"
                                                maxLength={40}
                                            />
                                            <input type="color" value={newColor.hex} onChange={(e) => setNewColor({ ...newColor, hex: e.target.value })} className="h-8 w-8 cursor-pointer rounded-full border-0 bg-transparent p-0" aria-label={t('edit.color_pick')} />
                                            <Button size="sm" onClick={addColor}>
                                                {t('edit.add')}
                                            </Button>
                                        </span>
                                    ) : (
                                        <button type="button" onClick={() => setAdding(true)} className="inline-flex h-8 items-center rounded-full border border-dashed border-line-control px-3 text-caption text-ink-muted hover:bg-secondary">
                                            + {t('edit.add')}
                                        </button>
                                    )}
                                </div>
                            ) : null}
                        </div>

                        {/* More details */}
                        <p className="mt-7 text-caption text-ink-muted">{t('edit.more')}</p>
                        <div className="mt-1">
                            <DetailRow id="description" title={t('edit.description')} summary={data.description || t('edit.description_empty')} open={open === 'description'} onToggle={() => setOpen(open === 'description' ? null : 'description')}>
                                <Field label={t('edit.description')} htmlFor="description" error={errors.description}>
                                    <Textarea id="description" rows={4} value={data.description} onChange={(e) => setData('description', e.target.value)} maxLength={5000} />
                                </Field>
                            </DetailRow>
                            <DetailRow id="category" title={t('edit.category')} summary={data.category || t('edit.category_empty')} open={open === 'category'} onToggle={() => setOpen(open === 'category' ? null : 'category')}>
                                <Field label={t('edit.category')} htmlFor="category" error={errors.category}>
                                    <Input id="category" list="category-list" value={data.category} onChange={(e) => setData('category', e.target.value)} maxLength={80} />
                                </Field>
                                <datalist id="category-list">
                                    {categories.map((c) => (
                                        <option key={c} value={c} />
                                    ))}
                                </datalist>
                            </DetailRow>
                            <DetailRow id="seo" title={t('edit.seo')} summary={data.seo_title || t('edit.seo_empty')} open={open === 'seo'} onToggle={() => setOpen(open === 'seo' ? null : 'seo')}>
                                <Field label={t('edit.seo_title')} htmlFor="seo_title" error={errors.seo_title} hint={t('edit.chars', { n: data.seo_title.length, max: 70 })}>
                                    <Input id="seo_title" value={data.seo_title} onChange={(e) => setData('seo_title', e.target.value)} maxLength={70} placeholder={data.name} />
                                </Field>
                                <Field label={t('edit.seo_description')} htmlFor="seo_description" error={errors.seo_description} hint={t('edit.chars', { n: data.seo_description.length, max: 320 })}>
                                    <Textarea id="seo_description" rows={3} value={data.seo_description} onChange={(e) => setData('seo_description', e.target.value)} maxLength={320} />
                                </Field>
                            </DetailRow>
                            <DetailRow
                                id="pricing"
                                title={t('edit.cost')}
                                summary={data.cost || data.compare_at_price ? t('edit.cost_set') : t('edit.cost_empty')}
                                open={open === 'pricing'}
                                onToggle={() => setOpen(open === 'pricing' ? null : 'pricing')}
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label={t('edit.cost_label')} htmlFor="cost" error={errors.cost}>
                                        <Input id="cost" inputMode="decimal" dir="ltr" value={data.cost} onChange={(e) => setData('cost', e.target.value)} />
                                    </Field>
                                    <Field label={t('edit.compare_at')} htmlFor="compare_at_price" error={errors.compare_at_price}>
                                        <Input id="compare_at_price" inputMode="decimal" dir="ltr" value={data.compare_at_price} onChange={(e) => setData('compare_at_price', e.target.value)} />
                                    </Field>
                                </div>
                                {(() => {
                                    const cost = toPiasters(data.cost);
                                    if (price === null || cost === null || price <= 0) return null;
                                    const profit = price - cost;
                                    return (
                                        <p className="text-caption text-ink-muted">
                                            {t('edit.profit', { profit: formatPounds(profit, locale), margin: Math.round((profit / price) * 100) })}
                                        </p>
                                    );
                                })()}
                            </DetailRow>
                        </div>

                        {/* Delete */}
                        <div className="mt-6 flex flex-wrap items-center gap-2">
                            {confirmDelete ? (
                                <>
                                    <span className="text-sm text-danger">{t('edit.delete_confirm')}</span>
                                    <Button variant="danger" size="sm" onClick={() => router.delete(`/products/${product.id}`)}>
                                        <Trash2 className="h-4 w-4" aria-hidden />
                                        {t('edit.delete_yes')}
                                    </Button>
                                    <Button variant="ghost" size="sm" onClick={() => setConfirmDelete(false)}>
                                        {t('common.cancel')}
                                    </Button>
                                </>
                            ) : (
                                <Button variant="ghost" size="sm" className="!text-danger" onClick={() => setConfirmDelete(true)}>
                                    <Trash2 className="h-4 w-4" aria-hidden />
                                    {t('edit.delete')}
                                </Button>
                            )}
                        </div>
                    </Card>

                    {/* Preview */}
                    <div className="space-y-4 lg:sticky lg:top-6">
                        <Card className="p-6">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="font-display text-base font-normal">{t('edit.preview')}</h2>
                                <div role="group" aria-label={t('edit.preview_lang')} className="flex rounded-full bg-secondary p-1">
                                    {(['en', 'ar'] as const).map((l) => (
                                        <button
                                            key={l}
                                            type="button"
                                            aria-pressed={previewLang === l}
                                            onClick={() => setPreviewLang(l)}
                                            className={cn('h-8 rounded-full px-3 text-caption', previewLang === l ? 'bg-elevated shadow-sm' : 'text-ink-muted')}
                                        >
                                            {l === 'en' ? 'English' : 'عربي'}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <div className="mt-4 rounded-2xl bg-elevated p-4 shadow-md" dir={previewLang === 'ar' ? 'rtl' : 'ltr'} lang={previewLang} aria-label={t('edit.preview')}>
                                <div className="aspect-[414/300] overflow-hidden rounded-xl bg-accent-subtle">
                                    {cover ? (
                                        <img src={cover} alt="" className="h-full w-full object-cover" />
                                    ) : (
                                        <div className="grid h-full place-items-center">
                                            <ImageIcon className="h-8 w-8 text-ink-muted" aria-hidden strokeWidth={1.5} />
                                        </div>
                                    )}
                                </div>
                                <p className="mt-4 text-sm font-semibold">{previewName}</p>
                                <div className="mt-2 flex items-center justify-between gap-2">
                                    <span className="tabular text-base">{formatPounds(price ?? 0, previewLang)}</span>
                                    {data.has_options ? (
                                        <span className="flex gap-1.5" aria-hidden>
                                            {data.colors.map((c, i) => (
                                                <span key={i} className="h-3.5 w-3.5 rounded-full ring-1 ring-black/10" style={{ background: c.hex }} />
                                            ))}
                                        </span>
                                    ) : null}
                                </div>
                                <div aria-hidden className="mt-4 flex h-11 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-fg">
                                    {previewLang === 'ar' ? 'أضف إلى السلة' : 'Add to cart'}
                                </div>
                            </div>
                        </Card>

                        {product.source !== 'manual' && !isLive ? (
                            <Card className="flex gap-3 p-5">
                                <span className="orb mt-0.5 h-6 w-6 shrink-0 rounded-full" aria-hidden />
                                <div>
                                    <p className="text-sm">{t(`edit.note_${product.source}`)}</p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <Button size="sm" onClick={submit('publish')} disabled={processing}>
                                            {t('edit.looks_good')}
                                        </Button>
                                        <Button variant="ghost" size="sm" onClick={() => nameInput.current?.focus()}>
                                            {t('edit.change_it')}
                                        </Button>
                                    </div>
                                </div>
                            </Card>
                        ) : null}
                    </div>
                </div>
            </form>
        </DashboardLayout>
    );
}
