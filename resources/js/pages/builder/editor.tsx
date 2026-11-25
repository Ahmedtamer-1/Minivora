// Store builder: live pages, starter templates, ordered sections, publish & version restore.
import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    CheckCircle2,
    ExternalLink,
    Eye,
    EyeOff,
    FilePlus2,
    History,
    Home,
    Layers,
    Plus,
    RotateCcw,
    Sparkles,
    Trash2,
    UploadCloud,
} from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Checkbox, Field, Input, Select } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { formatDate, useLocale, useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

interface BuilderSection {
    id: number;
    page_id: number;
    type: string;
    sort_order: number;
    is_visible: boolean;
    settings: Record<string, unknown> | null;
}

interface BuilderPageVersion {
    id: number;
    page_id: number;
    version_number: number;
    snapshot: {
        title?: string;
        sections?: unknown[];
    } | null;
    created_at: string;
}

interface BuilderPage {
    id: number;
    slug: string;
    title: string;
    title_ar: string | null;
    is_home: boolean;
    status: string;
    is_ai_draft: boolean;
    published_at: string | null;
    updated_at: string;
    sections: BuilderSection[];
    versions: BuilderPageVersion[];
}

interface SectionTypeDef {
    label: string;
    label_ar: string;
    category: string;
    defaults: Record<string, unknown>;
}

interface BuilderEditorProps {
    pages?: BuilderPage[];
    sectionTypes?: Record<string, SectionTypeDef>;
    templates?: string[];
}

const templateLabels: Record<string, { en: string; ar: string }> = {
    fashion: { en: 'Fashion', ar: 'أزياء' },
    food: { en: 'Food & Bakery', ar: 'أطعمة ومخبوزات' },
    handmade: { en: 'Handmade', ar: 'مشغولات يدوية' },
    electronics: { en: 'Electronics', ar: 'إلكترونيات' },
};

function extractError(err: unknown): string {
    if (axios.isAxiosError(err)) {
        const data = err.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;
        if (data?.errors) {
            const first = Object.values(data.errors).flat()[0];
            if (first) return first;
        }
        if (data?.message) return data.message;
    }
    return 'Request failed. Please try again.';
}

export default function BuilderEditor({
    pages = [],
    sectionTypes = {},
    templates = ['fashion', 'food', 'handmade', 'electronics'],
}: BuilderEditorProps) {
    const t = useT();
    const locale = useLocale();
    const { store } = usePage<SharedProps>().props;

    const [activePageId, setActivePageId] = useState<number | null>(pages[0]?.id ?? null);
    const [showNewPage, setShowNewPage] = useState(false);
    const [newTitle, setNewTitle] = useState('');
    const [newTitleAr, setNewTitleAr] = useState('');
    const [newSlug, setNewSlug] = useState('');
    const [newIsHome, setNewIsHome] = useState(false);

    const sectionKeys = Object.keys(sectionTypes);
    const [newSectionType, setNewSectionType] = useState<string>(sectionKeys[0] ?? 'hero');
    const [newSectionHeading, setNewSectionHeading] = useState('');

    const [busy, setBusy] = useState(false);
    const [notice, setNotice] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null);

    useEffect(() => {
        if (pages.length === 0) {
            setActivePageId(null);
        } else if (!pages.some((p) => p.id === activePageId)) {
            setActivePageId(pages[0].id);
        }
    }, [pages, activePageId]);

    useEffect(() => {
        if (sectionKeys.length > 0 && !sectionKeys.includes(newSectionType)) {
            setNewSectionType(sectionKeys[0]);
        }
    }, [sectionKeys, newSectionType]);

    const activePage = pages.find((p) => p.id === activePageId) ?? pages[0] ?? null;

    const pageLabel = (p: BuilderPage) => (locale === 'ar' && p.title_ar ? p.title_ar : p.title);

    const sectionLabel = (type: string) => {
        const def = sectionTypes[type];
        if (!def) return type;
        return locale === 'ar' ? def.label_ar : def.label;
    };

    const reload = (message?: string) => {
        if (message) {
            setNotice({ tone: 'success', text: message });
        }
        router.reload();
    };

    const handleCreatePage = async (e: FormEvent) => {
        e.preventDefault();
        if (!newTitle.trim()) return;
        setBusy(true);
        setNotice(null);
        try {
            const { data } = await axios.post<{ page: BuilderPage }>('/builder/pages', {
                title: newTitle.trim(),
                title_ar: newTitleAr.trim() || null,
                slug: newSlug.trim() || null,
                is_home: newIsHome,
            });
            setNewTitle('');
            setNewTitleAr('');
            setNewSlug('');
            setNewIsHome(false);
            setShowNewPage(false);
            if (data?.page?.id) {
                setActivePageId(data.page.id);
            }
            reload('Page created.');
        } catch (err) {
            setNotice({ tone: 'danger', text: extractError(err) });
        } finally {
            setBusy(false);
        }
    };

    const handleApplyTemplate = (businessType: string) => {
        setBusy(true);
        setNotice(null);
        router.post(
            '/builder/template',
            { business_type: businessType },
            {
                preserveScroll: true,
                onSuccess: () => setNotice({ tone: 'success', text: `Applied "${businessType}" starter template.` }),
                onError: () => setNotice({ tone: 'danger', text: 'Could not apply starter template.' }),
                onFinish: () => setBusy(false),
            },
        );
    };

    const handleAddSection = async (e: FormEvent) => {
        e.preventDefault();
        if (!activePage || !newSectionType) return;
        setBusy(true);
        setNotice(null);
        try {
            const settings: Record<string, unknown> = {};
            if (newSectionHeading.trim()) {
                settings.heading = newSectionHeading.trim();
                settings.headline = newSectionHeading.trim();
            }
            await axios.post(`/builder/pages/${activePage.id}/sections`, {
                type: newSectionType,
                settings,
                is_visible: true,
            });
            setNewSectionHeading('');
            reload('Section added.');
        } catch (err) {
            setNotice({ tone: 'danger', text: extractError(err) });
        } finally {
            setBusy(false);
        }
    };

    const handleToggleVisibility = async (section: BuilderSection) => {
        setBusy(true);
        setNotice(null);
        try {
            await axios.put(`/builder/sections/${section.id}`, {
                settings: section.settings ?? {},
                is_visible: !section.is_visible,
            });
            reload(section.is_visible ? 'Section hidden.' : 'Section visible.');
        } catch (err) {
            setNotice({ tone: 'danger', text: extractError(err) });
        } finally {
            setBusy(false);
        }
    };

    const handleDeleteSection = async (section: BuilderSection) => {
        setBusy(true);
        setNotice(null);
        try {
            await axios.delete(`/builder/sections/${section.id}`);
            reload('Section deleted.');
        } catch (err) {
            setNotice({ tone: 'danger', text: extractError(err) });
        } finally {
            setBusy(false);
        }
    };

    const handlePublishPage = async () => {
        if (!activePage) return;
        setBusy(true);
        setNotice(null);
        try {
            await axios.post(`/builder/pages/${activePage.id}/publish`);
            reload('Page published and snapshot saved.');
        } catch (err) {
            setNotice({ tone: 'danger', text: extractError(err) });
        } finally {
            setBusy(false);
        }
    };

    const handleRestoreVersion = async (version: BuilderPageVersion) => {
        if (!activePage) return;
        setBusy(true);
        setNotice(null);
        try {
            await axios.post(`/builder/pages/${activePage.id}/versions/${version.id}/restore`);
            reload(`Restored version v${version.version_number}.`);
        } catch (err) {
            setNotice({ tone: 'danger', text: extractError(err) });
        } finally {
            setBusy(false);
        }
    };

    return (
        <DashboardLayout title={t('nav.builder')}>
            <PageHeader
                title={t('builder.title')}
                description={t('builder.subtitle')}
                action={
                    <>
                        {store ? (
                            <a
                                href="/preview"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex h-10 items-center gap-2 rounded-full px-4 text-sm font-semibold text-ink-link hover:bg-secondary"
                            >
                                <ExternalLink className="h-4 w-4" aria-hidden />
                                {t('nav.view_store')}
                            </a>
                        ) : null}
                        <Button variant={showNewPage ? 'secondary' : 'primary'} onClick={() => setShowNewPage((v) => !v)}>
                            <FilePlus2 className="h-4 w-4" aria-hidden />
                            {t('builder.add_page')}
                        </Button>
                    </>
                }
            />

            {notice ? (
                <div
                    role="status"
                    className={cn(
                        'mb-6 flex items-center justify-between gap-3 rounded-2xl px-4 py-3 text-sm',
                        notice.tone === 'success' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger',
                    )}
                >
                    <span>{notice.text}</span>
                    <button type="button" onClick={() => setNotice(null)} className="text-xs font-semibold underline">
                        Dismiss
                    </button>
                </div>
            ) : null}

            {showNewPage ? (
                <Card className="mb-6">
                    <CardHeader title="Create new page" description="Add a draft storefront page and compose it with sections." />
                    <CardBody>
                        <form onSubmit={handleCreatePage} className="grid gap-4 sm:grid-cols-3">
                            <Field label="Title (EN)" htmlFor="page-title-en">
                                <Input
                                    id="page-title-en"
                                    value={newTitle}
                                    onChange={(e) => setNewTitle(e.target.value)}
                                    placeholder="About Us"
                                    required
                                />
                            </Field>
                            <Field label="Title (AR)" htmlFor="page-title-ar">
                                <Input
                                    id="page-title-ar"
                                    value={newTitleAr}
                                    onChange={(e) => setNewTitleAr(e.target.value)}
                                    placeholder="من نحن"
                                    dir="rtl"
                                />
                            </Field>
                            <Field label="Slug (optional)" htmlFor="page-slug">
                                <Input
                                    id="page-slug"
                                    value={newSlug}
                                    onChange={(e) => setNewSlug(e.target.value)}
                                    placeholder="about-us"
                                    dir="ltr"
                                />
                            </Field>
                            <div className="flex flex-wrap items-center justify-between gap-4 sm:col-span-3">
                                <Checkbox
                                    label="Set as store homepage"
                                    checked={newIsHome}
                                    onChange={(e) => setNewIsHome(e.target.checked)}
                                />
                                <div className="flex gap-2">
                                    <Button variant="ghost" onClick={() => setShowNewPage(false)}>
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={busy || !newTitle.trim()}>
                                        Create page
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </CardBody>
                </Card>
            ) : null}

            {/* Starter Templates */}
            <Card className="mb-6">
                <CardHeader
                    title="Starter templates"
                    description="Apply a ready-made homepage layout and theme tailored to your business type."
                />
                <CardBody>
                    <div className="flex flex-wrap gap-2">
                        {templates.map((tpl) => {
                            const label = templateLabels[tpl]
                                ? locale === 'ar'
                                    ? templateLabels[tpl].ar
                                    : templateLabels[tpl].en
                                : tpl;
                            return (
                                <Button
                                    key={tpl}
                                    variant="secondary"
                                    size="sm"
                                    disabled={busy}
                                    onClick={() => handleApplyTemplate(tpl)}
                                >
                                    <Sparkles className="h-3.5 w-3.5" aria-hidden />
                                    {label}
                                </Button>
                            );
                        })}
                    </div>
                </CardBody>
            </Card>

            {/* Page Tabs */}
            <div className="mb-6 flex flex-wrap items-center gap-2" role="tablist" aria-label={t('builder.pages')}>
                {pages.map((p) => {
                    const selected = activePage?.id === p.id;
                    return (
                        <button
                            key={p.id}
                            type="button"
                            role="tab"
                            aria-selected={selected}
                            onClick={() => setActivePageId(p.id)}
                            className={cn(
                                'inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition-colors',
                                selected
                                    ? 'border-line-focus bg-primary text-primary-fg'
                                    : 'border-line bg-elevated text-ink hover:bg-secondary',
                            )}
                        >
                            {p.is_home ? <Home className="h-3.5 w-3.5" aria-hidden /> : null}
                            <span>{pageLabel(p)}</span>
                            <span
                                className={cn(
                                    'rounded-full px-1.5 py-0.5 text-xs',
                                    selected ? 'bg-white/20 text-primary-fg' : 'bg-secondary text-ink-muted',
                                )}
                            >
                                {p.sections.length}
                            </span>
                        </button>
                    );
                })}
                {pages.length === 0 ? (
                    <p className="text-sm text-ink-muted">No pages yet. Create a page or apply a starter template above.</p>
                ) : null}
            </div>

            {activePage ? (
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    {/* Sections Column */}
                    <Card>
                        <CardHeader
                            title={
                                <span className="flex flex-wrap items-center gap-2">
                                    <span>{pageLabel(activePage)}</span>
                                    <Badge tone={activePage.status === 'published' ? 'success' : 'warning'}>
                                        {activePage.status === 'published' ? t('builder.status_published') : t('builder.status_draft')}
                                    </Badge>
                                    <span dir="ltr" className="font-mono text-xs text-ink-muted">
                                        /{activePage.slug}
                                    </span>
                                </span>
                            }
                            description={t('builder.sections', { count: activePage.sections.length })}
                            action={
                                <Button size="sm" disabled={busy} onClick={handlePublishPage}>
                                    <UploadCloud className="h-4 w-4" aria-hidden />
                                    Publish page
                                </Button>
                            }
                        />
                        <CardBody className="space-y-6">
                            {/* Add Section Form */}
                            <form
                                onSubmit={handleAddSection}
                                className="grid gap-3 rounded-2xl border border-line bg-secondary/40 p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                            >
                                <Field label="Section type" htmlFor="section-type">
                                    <Select
                                        id="section-type"
                                        value={newSectionType}
                                        onChange={(e) => setNewSectionType(e.target.value)}
                                    >
                                        {sectionKeys.map((key) => (
                                            <option key={key} value={key}>
                                                {sectionLabel(key)} ({sectionTypes[key].category})
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label="Heading (optional)" htmlFor="section-heading">
                                    <Input
                                        id="section-heading"
                                        value={newSectionHeading}
                                        onChange={(e) => setNewSectionHeading(e.target.value)}
                                        placeholder="New Collection"
                                    />
                                </Field>
                                <Button type="submit" disabled={busy || !newSectionType}>
                                    <Plus className="h-4 w-4" aria-hidden />
                                    Add section
                                </Button>
                            </form>

                            {/* Ordered Sections List */}
                            {activePage.sections.length === 0 ? (
                                <div className="rounded-2xl border border-dashed border-line p-8 text-center text-sm text-ink-muted">
                                    <Layers className="mx-auto mb-2 h-6 w-6 opacity-60" aria-hidden />
                                    This page has no sections yet. Add your first section above.
                                </div>
                            ) : (
                                <ul className="divide-y divide-line">
                                    {activePage.sections.map((section, idx) => {
                                        const headingText =
                                            (typeof section.settings?.headline === 'string' && section.settings.headline) ||
                                            (typeof section.settings?.heading === 'string' && section.settings.heading) ||
                                            null;
                                        return (
                                            <li
                                                key={section.id}
                                                className={cn(
                                                    'flex flex-wrap items-center justify-between gap-3 py-3',
                                                    !section.is_visible && 'opacity-60',
                                                )}
                                            >
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-secondary font-mono text-xs text-ink-muted">
                                                        {idx + 1}
                                                    </span>
                                                    <div className="min-w-0">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span className="text-sm font-medium">{sectionLabel(section.type)}</span>
                                                            <Badge tone={section.is_visible ? 'success' : 'neutral'}>
                                                                {section.is_visible ? 'Visible' : 'Hidden'}
                                                            </Badge>
                                                        </div>
                                                        {headingText ? (
                                                            <p className="truncate text-xs text-ink-muted">{headingText}</p>
                                                        ) : (
                                                            <p dir="ltr" className="font-mono text-xs text-ink-muted">
                                                                {section.type}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        disabled={busy}
                                                        onClick={() => handleToggleVisibility(section)}
                                                        aria-label={section.is_visible ? 'Hide section' : 'Show section'}
                                                    >
                                                        {section.is_visible ? (
                                                            <>
                                                                <EyeOff className="h-3.5 w-3.5" aria-hidden />
                                                                Hide
                                                            </>
                                                        ) : (
                                                            <>
                                                                <Eye className="h-3.5 w-3.5" aria-hidden />
                                                                Show
                                                            </>
                                                        )}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="danger"
                                                        disabled={busy}
                                                        onClick={() => handleDeleteSection(section)}
                                                        aria-label="Delete section"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" aria-hidden />
                                                        Delete
                                                    </Button>
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </CardBody>
                    </Card>

                    {/* Version History Column */}
                    <Card>
                        <CardHeader
                            title={
                                <span className="inline-flex items-center gap-2">
                                    <History className="h-4 w-4 text-ink-muted" aria-hidden />
                                    Version history
                                </span>
                            }
                            description="Every publish saves an immutable snapshot you can restore."
                        />
                        <CardBody>
                            {activePage.versions.length === 0 ? (
                                <p className="text-sm text-ink-muted">
                                    No published versions yet. Click Publish page to create version v1.
                                </p>
                            ) : (
                                <ul className="divide-y divide-line">
                                    {activePage.versions.map((ver, idx) => (
                                        <li key={ver.id} className="flex items-center justify-between gap-3 py-3">
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-mono text-sm font-semibold">v{ver.version_number}</span>
                                                    {idx === 0 ? (
                                                        <Badge tone="success">
                                                            <CheckCircle2 className="h-3 w-3" aria-hidden />
                                                            Latest
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <p className="mt-0.5 text-xs text-ink-muted">
                                                    {formatDate(ver.created_at, locale, true)} ·{' '}
                                                    {Array.isArray(ver.snapshot?.sections) ? ver.snapshot.sections.length : 0} sections
                                                </p>
                                            </div>
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                disabled={busy}
                                                onClick={() => handleRestoreVersion(ver)}
                                            >
                                                <RotateCcw className="h-3.5 w-3.5" aria-hidden />
                                                Restore
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardBody>
                    </Card>
                </div>
            ) : null}
        </DashboardLayout>
    );
}
