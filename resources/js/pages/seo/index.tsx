// SEO health: live crawler/feed links, 301 redirects manager, top 404 hits table, and on-page SEO audit.
import { router } from '@inertiajs/react';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    ExternalLink,
    FileCode2,
    Plus,
    Rss,
    SearchCheck,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Checkbox, Field, Input, Select, Textarea } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { formatDate, useLocale, useT } from '@/lib/i18n';

interface SeoStoreInfo {
    subdomain?: string;
    domain?: string;
    url?: string;
}

interface SeoRedirect {
    id: number;
    from_path: string;
    to_path: string;
    status_code: number;
    is_automatic: boolean;
    hits: number;
    created_at: string;
}

interface NotFoundEntry {
    path: string;
    hits: number;
    last_hit_at: string | null;
}

interface SeoSettings {
    default_title_en: string | null;
    default_title_ar: string | null;
    default_description_en: string | null;
    default_description_ar: string | null;
    gsc_verification_token: string | null;
}

interface AuditIssue {
    code: string;
    severity: 'error' | 'warning' | string;
    message: string;
}

interface AuditResult {
    score: number;
    issues: AuditIssue[];
}

interface SeoPageProps {
    store?: SeoStoreInfo | null;
    redirects?: SeoRedirect[];
    notFound?: NotFoundEntry[];
    settings?: SeoSettings | null;
}

const crawlerFiles = [
    { key: 'sitemap', label: 'Sitemap XML', icon: FileCode2, path: '/sitemap.xml' },
    { key: 'robots', label: 'Robots.txt', icon: FileCode2, path: '/robots.txt' },
    { key: 'llms', label: 'AI Search (llms.txt)', icon: FileCode2, path: '/llms.txt' },
    { key: 'google_feed', label: 'Google Merchant Feed', icon: Rss, path: '/feeds/google.xml' },
    { key: 'meta_feed', label: 'Meta Catalog Feed', icon: Rss, path: '/feeds/meta.tsv' },
];

/** Circular score, 0–100. */
function Score({ value, label }: { value: number; label: string }) {
    const clamped = Math.max(0, Math.min(100, value));
    const r = 52;
    const c = 2 * Math.PI * r;
    const strokeClass = clamped >= 80 ? 'stroke-success' : clamped >= 50 ? 'stroke-warning' : 'stroke-danger';

    return (
        <div className="relative grid h-36 w-36 place-items-center">
            <svg viewBox="0 0 120 120" className="absolute inset-0 -rotate-90" aria-hidden>
                <circle cx="60" cy="60" r={r} fill="none" strokeWidth="10" className="stroke-chart-track" />
                <circle
                    cx="60"
                    cy="60"
                    r={r}
                    fill="none"
                    strokeWidth="10"
                    strokeLinecap="round"
                    className={strokeClass}
                    strokeDasharray={c}
                    strokeDashoffset={c * (1 - clamped / 100)}
                />
            </svg>
            <p className="text-center">
                <span className="tabular block font-display text-numeral-sm">{clamped}</span>
                <span className="block text-caption text-ink-muted">{label}</span>
            </p>
        </div>
    );
}

export default function Seo({ store = null, redirects = [], notFound = [], settings = null }: SeoPageProps) {
    const t = useT();
    const locale = useLocale();

    // Redirect form state
    const [fromPath, setFromPath] = useState('');
    const [toPath, setToPath] = useState('');
    const [statusCode, setStatusCode] = useState('301');
    const [redirectBusy, setRedirectBusy] = useState(false);

    // SEO Audit form state
    const [auditTitle, setAuditTitle] = useState(settings?.default_title_en ?? '');
    const [auditDescription, setAuditDescription] = useState(settings?.default_description_en ?? '');
    const [auditH1Count, setAuditH1Count] = useState('1');
    const [auditContent, setAuditContent] = useState('');
    const [auditJsonLd, setAuditJsonLd] = useState(true);
    const [auditBusy, setAuditBusy] = useState(false);
    const [auditResult, setAuditResult] = useState<AuditResult | null>(null);

    const [notice, setNotice] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null);

    const baseUrl =
        store?.subdomain && store?.domain
            ? `https://${store.subdomain}.${store.domain}`
            : store?.url
              ? store.url.replace(/\/$/, '')
              : '';

    const handleAddRedirect = async (e: FormEvent) => {
        e.preventDefault();
        if (!fromPath.trim() || !toPath.trim()) return;
        setRedirectBusy(true);
        setNotice(null);
        try {
            await axios.post('/seo/redirects', {
                from_path: fromPath.trim(),
                to_path: toPath.trim(),
                status_code: Number(statusCode) || 301,
            });
            setFromPath('');
            setToPath('');
            setNotice({ tone: 'success', text: 'Redirect rule saved.' });
            router.reload();
        } catch (err) {
            const msg = axios.isAxiosError(err)
                ? ((err.response?.data as { message?: string })?.message ?? 'Could not save redirect.')
                : 'Could not save redirect.';
            setNotice({ tone: 'danger', text: msg });
        } finally {
            setRedirectBusy(false);
        }
    };

    const handleDeleteRedirect = async (id: number) => {
        setRedirectBusy(true);
        setNotice(null);
        try {
            await axios.delete(`/seo/redirects/${id}`);
            setNotice({ tone: 'success', text: 'Redirect deleted.' });
            router.reload();
        } catch (err) {
            const msg = axios.isAxiosError(err)
                ? ((err.response?.data as { message?: string })?.message ?? 'Could not delete redirect.')
                : 'Could not delete redirect.';
            setNotice({ tone: 'danger', text: msg });
        } finally {
            setRedirectBusy(false);
        }
    };

    const handleAudit = async (e: FormEvent) => {
        e.preventDefault();
        setAuditBusy(true);
        setNotice(null);
        try {
            const { data } = await axios.post<AuditResult>('/seo/audit', {
                title: auditTitle,
                description: auditDescription,
                h1_count: Math.max(0, Number(auditH1Count) || 0),
                content: auditContent,
                json_ld: auditJsonLd,
            });
            setAuditResult(data);
        } catch (err) {
            const msg = axios.isAxiosError(err)
                ? ((err.response?.data as { message?: string })?.message ?? 'SEO audit failed.')
                : 'SEO audit failed.';
            setNotice({ tone: 'danger', text: msg });
        } finally {
            setAuditBusy(false);
        }
    };

    return (
        <DashboardLayout title={t('nav.seo')}>
            <PageHeader title={t('seo.title')} description={t('seo.subtitle')} />

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

            {/* Top row: Crawler/Feed Links + Live Page SEO Audit */}
            <div className="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]">
                {/* Live Crawler & Feed Links */}
                <Card>
                    <CardHeader title={t('seo.files')} description={t('seo.files_hint')} />
                    <CardBody>
                        <ul className="divide-y divide-line">
                            {crawlerFiles.map(({ key, label, icon: Icon, path }) => {
                                const href = `${baseUrl}${path}`;
                                return (
                                    <li key={key} className="flex min-h-[56px] items-center gap-3 py-2">
                                        <Icon className="h-4 w-4 shrink-0 text-ink-muted" aria-hidden />
                                        <div className="min-w-0 flex-1">
                                            <span className="block text-sm font-medium">{label}</span>
                                            <a
                                                href={href}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                dir="ltr"
                                                className="inline-flex max-w-full items-center gap-1 truncate font-mono text-xs text-ink-link hover:underline"
                                            >
                                                <span className="truncate">{href}</span>
                                                <ExternalLink className="h-3 w-3 shrink-0" aria-hidden />
                                            </a>
                                        </div>
                                        <Badge tone="success">{t('seo.live')}</Badge>
                                    </li>
                                );
                            })}
                        </ul>
                    </CardBody>
                </Card>

                {/* Live Page SEO Audit */}
                <Card>
                    <CardHeader
                        title="Page SEO audit"
                        description="Check meta title, description, H1 count, content depth, and JSON-LD for a live 0–100 score."
                    />
                    <CardBody className="space-y-5">
                        <form onSubmit={handleAudit} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-[1fr_120px]">
                                <Field label="Meta title (30–60 chars)" htmlFor="audit-title">
                                    <Input
                                        id="audit-title"
                                        value={auditTitle}
                                        onChange={(e) => setAuditTitle(e.target.value)}
                                        placeholder="Handcrafted Leather Bags in Cairo | Minivora"
                                    />
                                </Field>
                                <Field label="H1 count" htmlFor="audit-h1">
                                    <Input
                                        id="audit-h1"
                                        type="number"
                                        min={0}
                                        value={auditH1Count}
                                        onChange={(e) => setAuditH1Count(e.target.value)}
                                    />
                                </Field>
                            </div>

                            <Field label="Meta description (70–155 chars)" htmlFor="audit-description">
                                <Textarea
                                    id="audit-description"
                                    rows={2}
                                    value={auditDescription}
                                    onChange={(e) => setAuditDescription(e.target.value)}
                                    placeholder="Shop handmade leather totes, wallets, and accessories crafted in Egypt with fast delivery."
                                />
                            </Field>

                            <Field label="Page content (recommended 150+ words)" htmlFor="audit-content">
                                <Textarea
                                    id="audit-content"
                                    rows={3}
                                    value={auditContent}
                                    onChange={(e) => setAuditContent(e.target.value)}
                                    placeholder="Paste page body copy here to check content depth…"
                                />
                            </Field>

                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <Checkbox
                                    label="Includes JSON-LD structured data"
                                    checked={auditJsonLd}
                                    onChange={(e) => setAuditJsonLd(e.target.checked)}
                                />
                                <Button type="submit" disabled={auditBusy}>
                                    <SearchCheck className="h-4 w-4" aria-hidden />
                                    {auditBusy ? 'Auditing…' : 'Run SEO audit'}
                                </Button>
                            </div>
                        </form>

                        {auditResult ? (
                            <div className="grid gap-4 rounded-2xl border border-line bg-secondary/40 p-4 sm:grid-cols-[auto_1fr] sm:items-center">
                                <div className="flex justify-center">
                                    <Score value={auditResult.score} label={t('seo.out_of')} />
                                </div>
                                <div>
                                    {auditResult.issues.length === 0 ? (
                                        <div className="flex items-center gap-2 text-sm font-medium text-success">
                                            <CheckCircle2 className="h-5 w-5" aria-hidden />
                                            All on-page SEO checks passed!
                                        </div>
                                    ) : (
                                        <ul className="divide-y divide-line">
                                            {auditResult.issues.map((issue, i) => {
                                                const isError = issue.severity === 'error';
                                                return (
                                                    <li key={`${issue.code}-${i}`} className="flex items-start gap-2.5 py-2 text-sm">
                                                        {isError ? (
                                                            <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-danger" aria-hidden />
                                                        ) : (
                                                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-warning" aria-hidden />
                                                        )}
                                                        <span className="flex-1">{issue.message}</span>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    )}
                                </div>
                            </div>
                        ) : null}
                    </CardBody>
                </Card>
            </div>

            {/* Bottom row: 301 Redirects Manager + Top 404 Hits */}
            <div className="grid gap-6 lg:grid-cols-2">
                {/* 301 Redirects Manager */}
                <Card>
                    <CardHeader
                        title={`301 Redirects (${redirects.length})`}
                        description="Forward old or changed URLs so shoppers and search engines never hit a dead end."
                    />
                    <CardBody className="space-y-5">
                        <form onSubmit={handleAddRedirect} className="grid gap-3 sm:grid-cols-[1fr_1fr_92px_auto] sm:items-end">
                            <Field label="From path" htmlFor="redirect-from">
                                <Input
                                    id="redirect-from"
                                    value={fromPath}
                                    onChange={(e) => setFromPath(e.target.value)}
                                    placeholder="/old-product"
                                    dir="ltr"
                                    required
                                />
                            </Field>
                            <Field label="To path" htmlFor="redirect-to">
                                <Input
                                    id="redirect-to"
                                    value={toPath}
                                    onChange={(e) => setToPath(e.target.value)}
                                    placeholder="/products/new-product"
                                    dir="ltr"
                                    required
                                />
                            </Field>
                            <Field label="Code" htmlFor="redirect-code">
                                <Select
                                    id="redirect-code"
                                    value={statusCode}
                                    onChange={(e) => setStatusCode(e.target.value)}
                                >
                                    <option value="301">301</option>
                                    <option value="302">302</option>
                                    <option value="307">307</option>
                                    <option value="308">308</option>
                                </Select>
                            </Field>
                            <Button type="submit" disabled={redirectBusy || !fromPath.trim() || !toPath.trim()}>
                                <Plus className="h-4 w-4" aria-hidden />
                                Add
                            </Button>
                        </form>

                        {redirects.length === 0 ? (
                            <p className="text-sm text-ink-muted">No redirects configured yet.</p>
                        ) : (
                            <ul className="divide-y divide-line">
                                {redirects.map((r) => (
                                    <li key={r.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm">
                                        <div className="min-w-0 flex-1">
                                            <div dir="ltr" className="flex flex-wrap items-center gap-1.5 font-mono text-xs">
                                                <span className="truncate font-medium text-ink">{r.from_path}</span>
                                                <ArrowRight className="h-3.5 w-3.5 shrink-0 text-ink-muted" aria-hidden />
                                                <span className="truncate text-ink-link">{r.to_path}</span>
                                            </div>
                                            <div className="mt-1 flex items-center gap-2 text-xs text-ink-muted">
                                                <Badge tone="neutral" className="h-5 text-xs">
                                                    {r.status_code}
                                                </Badge>
                                                {r.is_automatic ? <span>Automatic</span> : <span>Manual</span>}
                                                <span>· {r.hits} hits</span>
                                            </div>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="danger"
                                            disabled={redirectBusy}
                                            onClick={() => handleDeleteRedirect(r.id)}
                                            aria-label={`Delete redirect from ${r.from_path}`}
                                        >
                                            <Trash2 className="h-3.5 w-3.5" aria-hidden />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardBody>
                </Card>

                {/* Top 404 Hits */}
                <Card>
                    <CardHeader
                        title={`Top 404 hits (${notFound.length})`}
                        description="Broken URLs visitors tried to open. Turn frequent 404s into 301 redirects."
                    />
                    <CardBody>
                        {notFound.length === 0 ? (
                            <p className="text-sm text-ink-muted">No 404 hits recorded yet.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-start text-sm">
                                    <thead>
                                        <tr className="border-b border-line text-xs text-ink-muted">
                                            <th className="pb-2 text-start font-medium">Path</th>
                                            <th className="pb-2 text-end font-medium">Hits</th>
                                            <th className="pb-2 text-end font-medium">Last seen</th>
                                            <th className="pb-2 text-end font-medium">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {notFound.map((row) => (
                                            <tr key={row.path}>
                                                <td dir="ltr" className="max-w-[200px] truncate py-2.5 font-mono text-xs">
                                                    {row.path}
                                                </td>
                                                <td className="tabular py-2.5 text-end font-medium">{row.hits}</td>
                                                <td className="py-2.5 text-end text-xs text-ink-muted">
                                                    {row.last_hit_at ? formatDate(row.last_hit_at, locale) : '—'}
                                                </td>
                                                <td className="py-2.5 text-end">
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        onClick={() => {
                                                            setFromPath(row.path);
                                                            setToPath('/');
                                                        }}
                                                    >
                                                        Redirect
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardBody>
                </Card>
            </div>
        </DashboardLayout>
    );
}
