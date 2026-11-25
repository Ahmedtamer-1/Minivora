// Categories: live category management with bilingual names and product counts. FR-CAT-03, FR-CAT-01
import { router, useForm } from '@inertiajs/react';
import { Plus, Tags, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Field, Input } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatNumber, useLocale, useT } from '@/lib/i18n';

interface CategoryRow {
    id: number;
    name: string;
    name_ar: string | null;
    slug: string;
    sort_order: number;
    products_count: number;
}

interface Props {
    categories?: CategoryRow[];
}

export default function Categories({ categories = [] }: Props) {
    const t = useT();
    const locale = useLocale();
    const isAr = locale === 'ar';
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const form = useForm({
        name: '',
        name_ar: '',
        slug: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/categories', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const remove = (category: CategoryRow) => {
        setDeletingId(category.id);
        router.delete(`/categories/${category.id}`, {
            preserveScroll: true,
            onFinish: () => setDeletingId(null),
        });
    };

    return (
        <DashboardLayout title={t('nav.products_categories')}>
            <PageHeader title={t('categories.title')} description={t('categories.subtitle')} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card>
                    <CardHeader
                        title={t('categories.list')}
                        description={t('categories.list_hint')}
                        action={<Badge tone="neutral">{formatNumber(categories.length)}</Badge>}
                    />
                    <CardBody>
                        {categories.length === 0 ? (
                            <p className="py-6 text-center text-sm text-ink-muted">
                                {isAr ? 'لا توجد تصنيفات بعد. أضف أول تصنيف من النموذج الجانبي.' : 'No categories yet. Add your first category using the form.'}
                            </p>
                        ) : (
                            <ul className="-mx-2 divide-y divide-line">
                                {categories.map((c) => {
                                    const primaryName = isAr ? c.name_ar || c.name : c.name;
                                    const secondaryName = isAr ? (c.name_ar ? c.name : null) : c.name_ar;

                                    return (
                                        <li key={c.id} className="flex min-h-[60px] items-center gap-3 px-2 py-2.5">
                                            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-accent-subtle text-ink-accent">
                                                <Tags className="h-4 w-4" aria-hidden strokeWidth={1.75} />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium text-ink">{primaryName}</p>
                                                <p className="flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                                                    {secondaryName ? <span>{secondaryName}</span> : null}
                                                    <span dir="ltr" className="font-mono">
                                                        /{c.slug}
                                                    </span>
                                                </p>
                                            </div>
                                            <Badge tone="neutral">{t('categories.count', { count: formatNumber(c.products_count) })}</Badge>
                                            <Button
                                                variant="danger"
                                                size="icon-sm"
                                                onClick={() => remove(c)}
                                                disabled={deletingId === c.id}
                                                aria-label={isAr ? `حذف ${primaryName}` : `Delete ${primaryName}`}
                                            >
                                                <Trash2 className="h-4 w-4" aria-hidden />
                                            </Button>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </CardBody>
                </Card>

                <Card className="self-start">
                    <CardHeader
                        title={t('categories.add')}
                        description={isAr ? 'أضف تصنيفاً جديداً لتنظيم منتجات متجرك.' : 'Create a new category to organize your storefront products.'}
                    />
                    <CardBody>
                        <form onSubmit={submit} className="space-y-4">
                            <Field label={isAr ? 'الاسم (بالإنجليزية)' : 'Name (English)'} htmlFor="cat_name" error={form.errors.name}>
                                <Input
                                    id="cat_name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder={isAr ? 'مثال: Skincare' : 'e.g. Skincare'}
                                    required
                                    maxLength={120}
                                />
                            </Field>
                            <Field label={isAr ? 'الاسم (بالعربية)' : 'Name (Arabic)'} htmlFor="cat_name_ar" error={form.errors.name_ar}>
                                <Input
                                    id="cat_name_ar"
                                    dir="rtl"
                                    value={form.data.name_ar}
                                    onChange={(e) => form.setData('name_ar', e.target.value)}
                                    placeholder="مثال: العناية بالبشرة"
                                    maxLength={120}
                                />
                            </Field>
                            <Field
                                label={isAr ? 'الرابط المختصر (اختياري)' : 'Slug (optional)'}
                                htmlFor="cat_slug"
                                error={form.errors.slug}
                                hint={isAr ? 'يُنشأ تلقائياً من الاسم إذا تُرك فارغاً.' : 'Auto-generated from the name if left blank.'}
                            >
                                <Input
                                    id="cat_slug"
                                    dir="ltr"
                                    value={form.data.slug}
                                    onChange={(e) => form.setData('slug', e.target.value)}
                                    placeholder="skincare"
                                    maxLength={120}
                                />
                            </Field>
                            <Button type="submit" disabled={form.processing || !form.data.name.trim()} className="w-full">
                                <Plus className="h-4 w-4" aria-hidden />
                                {form.processing ? t('common.saving') : t('categories.add')}
                            </Button>
                        </form>
                    </CardBody>
                </Card>
            </div>
        </DashboardLayout>
    );
}
