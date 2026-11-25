// Storefront theme: live editor for colours, fonts, corner radius, light/dark mode + live preview swatch.
import { router } from '@inertiajs/react';
import axios from 'axios';
import { Check, Moon, Palette, Save, Sun } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';

type CornerRadius = 'none' | 'sm' | 'md' | 'lg' | 'full';
type ColorMode = 'light' | 'dark';

interface StoreTheme {
    id?: number;
    primary_color: string;
    accent_color: string;
    font_heading: string;
    font_body: string;
    corner_radius: CornerRadius;
    color_mode: ColorMode;
    template?: string | null;
}

interface BuilderThemeProps {
    theme?: StoreTheme;
}

const palettes = [
    { key: 'nile', primary: '#1d7aa0', accent: '#7fe0c8' },
    { key: 'desert', primary: '#b5651d', accent: '#e09f3e' },
    { key: 'olive', primary: '#5b6b2f', accent: '#9bb65a' },
    { key: 'rose', primary: '#b23a5b', accent: '#e57a99' },
];

const fontOptions = ['Cairo', 'Outfit', 'Tajawal', 'IBM Plex Sans Arabic', 'Playfair Display', 'Amiri', 'Inter'];

const radiusOptions: { value: CornerRadius; label: string; css: string }[] = [
    { value: 'none', label: 'None (0px)', css: '0px' },
    { value: 'sm', label: 'Small (4px)', css: '0.25rem' },
    { value: 'md', label: 'Medium (8px)', css: '0.5rem' },
    { value: 'lg', label: 'Large (16px)', css: '1rem' },
    { value: 'full', label: 'Pill (9999px)', css: '9999px' },
];

const defaultTheme: StoreTheme = {
    primary_color: '#111827',
    accent_color: '#2563eb',
    font_heading: 'Cairo',
    font_body: 'Cairo',
    corner_radius: 'md',
    color_mode: 'light',
    template: null,
};

export default function BuilderTheme({ theme = defaultTheme }: BuilderThemeProps) {
    const t = useT();

    const [primaryColor, setPrimaryColor] = useState(theme.primary_color || '#111827');
    const [accentColor, setAccentColor] = useState(theme.accent_color || '#2563eb');
    const [fontHeading, setFontHeading] = useState(theme.font_heading || 'Cairo');
    const [fontBody, setFontBody] = useState(theme.font_body || 'Cairo');
    const [cornerRadius, setCornerRadius] = useState<CornerRadius>(theme.corner_radius || 'md');
    const [colorMode, setColorMode] = useState<ColorMode>(theme.color_mode || 'light');

    const [saving, setSaving] = useState(false);
    const [notice, setNotice] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null);

    const previewRadiusCss = radiusOptions.find((r) => r.value === cornerRadius)?.css ?? '0.5rem';

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setNotice(null);
        try {
            await axios.put('/builder/theme', {
                primary_color: primaryColor,
                accent_color: accentColor,
                font_heading: fontHeading,
                font_body: fontBody,
                corner_radius: cornerRadius,
                color_mode: colorMode,
            });
            setNotice({ tone: 'success', text: 'Theme settings saved.' });
            router.reload();
        } catch (err) {
            const msg = axios.isAxiosError(err)
                ? ((err.response?.data as { message?: string })?.message ?? 'Could not save theme.')
                : 'Could not save theme.';
            setNotice({ tone: 'danger', text: msg });
        } finally {
            setSaving(false);
        }
    };

    return (
        <DashboardLayout title={t('nav.builder_theme')}>
            <PageHeader
                title={t('theme.title')}
                description={t('theme.subtitle')}
                action={
                    theme.template ? (
                        <Badge tone="info">Template: {theme.template}</Badge>
                    ) : undefined
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

            <form onSubmit={handleSubmit} className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                <div className="grid gap-6 md:grid-cols-2">
                    {/* Colors */}
                    <Card>
                        <CardHeader title={t('theme.colors')} description="Pick brand colours or start from a curated palette." />
                        <CardBody className="space-y-4">
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Primary colour" htmlFor="primary-color">
                                    <div className="flex items-center gap-2">
                                        <input
                                            id="primary-color"
                                            type="color"
                                            value={primaryColor}
                                            onChange={(e) => setPrimaryColor(e.target.value)}
                                            className="h-10 w-12 cursor-pointer rounded-lg border border-line-control bg-elevated p-1"
                                        />
                                        <Input
                                            value={primaryColor}
                                            onChange={(e) => setPrimaryColor(e.target.value)}
                                            dir="ltr"
                                            className="font-mono"
                                            aria-label="Primary colour hex"
                                        />
                                    </div>
                                </Field>

                                <Field label="Accent colour" htmlFor="accent-color">
                                    <div className="flex items-center gap-2">
                                        <input
                                            id="accent-color"
                                            type="color"
                                            value={accentColor}
                                            onChange={(e) => setAccentColor(e.target.value)}
                                            className="h-10 w-12 cursor-pointer rounded-lg border border-line-control bg-elevated p-1"
                                        />
                                        <Input
                                            value={accentColor}
                                            onChange={(e) => setAccentColor(e.target.value)}
                                            dir="ltr"
                                            className="font-mono"
                                            aria-label="Accent colour hex"
                                        />
                                    </div>
                                </Field>
                            </div>

                            <div className="grid grid-cols-2 gap-2.5 pt-1">
                                {palettes.map((p) => {
                                    const active =
                                        primaryColor.toLowerCase() === p.primary.toLowerCase() &&
                                        accentColor.toLowerCase() === p.accent.toLowerCase();
                                    return (
                                        <button
                                            key={p.key}
                                            type="button"
                                            onClick={() => {
                                                setPrimaryColor(p.primary);
                                                setAccentColor(p.accent);
                                            }}
                                            className={cn(
                                                'rounded-2xl border p-2.5 text-start transition-colors',
                                                active ? 'border-line-focus ring-1 ring-line-focus' : 'border-line hover:bg-secondary/50',
                                            )}
                                        >
                                            <div className="flex gap-1.5" aria-hidden>
                                                <span className="h-6 flex-1 rounded-md" style={{ background: p.primary }} />
                                                <span className="h-6 flex-1 rounded-md" style={{ background: p.accent }} />
                                            </div>
                                            <span className="mt-1.5 block text-xs font-medium">{t(`theme.palette_${p.key}`)}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        </CardBody>
                    </Card>

                    {/* Typography */}
                    <Card>
                        <CardHeader title={t('theme.type')} description="Choose heading and body fonts for your storefront." />
                        <CardBody className="space-y-4">
                            <Field label="Heading font" htmlFor="font-heading">
                                <Select
                                    id="font-heading"
                                    value={fontHeading}
                                    onChange={(e) => setFontHeading(e.target.value)}
                                >
                                    {fontOptions.map((f) => (
                                        <option key={f} value={f}>
                                            {f}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <Field label="Body font" htmlFor="font-body">
                                <Select id="font-body" value={fontBody} onChange={(e) => setFontBody(e.target.value)}>
                                    {fontOptions.map((f) => (
                                        <option key={f} value={f}>
                                            {f}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <div className="rounded-2xl bg-secondary p-3.5">
                                <p className="text-lg font-semibold" style={{ fontFamily: `'${fontHeading}', system-ui` }}>
                                    Aa أبجد — {fontHeading}
                                </p>
                                <p className="mt-1 text-sm text-ink-muted" style={{ fontFamily: `'${fontBody}', system-ui` }}>
                                    The quick brown fox · أهلاً بكم في متجرنا ({fontBody})
                                </p>
                            </div>
                        </CardBody>
                    </Card>

                    {/* Corner Radius */}
                    <Card>
                        <CardHeader title={t('theme.shape')} description={t('theme.shape_hint')} />
                        <CardBody>
                            <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                                {radiusOptions.map((opt) => {
                                    const active = cornerRadius === opt.value;
                                    return (
                                        <button
                                            key={opt.value}
                                            type="button"
                                            onClick={() => setCornerRadius(opt.value)}
                                            className={cn(
                                                'flex flex-col items-center gap-2 rounded-2xl border p-3 text-xs font-medium transition-colors',
                                                active ? 'border-line-focus bg-secondary ring-1 ring-line-focus' : 'border-line hover:bg-secondary/50',
                                            )}
                                        >
                                            <span
                                                className="grid h-9 w-16 place-items-center bg-primary/15 text-ink"
                                                style={{ borderRadius: opt.css }}
                                                aria-hidden
                                            >
                                                Aa
                                            </span>
                                            <span>{opt.label}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        </CardBody>
                    </Card>

                    {/* Color Mode */}
                    <Card>
                        <CardHeader title={t('theme.mode')} description="Choose the default appearance for your storefront." />
                        <CardBody className="space-y-4">
                            <div className="grid grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    onClick={() => setColorMode('light')}
                                    className={cn(
                                        'flex items-center justify-between gap-2 rounded-2xl border p-3.5 text-sm font-medium transition-colors',
                                        colorMode === 'light'
                                            ? 'border-line-focus bg-secondary ring-1 ring-line-focus'
                                            : 'border-line hover:bg-secondary/50',
                                    )}
                                >
                                    <span className="inline-flex items-center gap-2">
                                        <Sun className="h-4 w-4" aria-hidden />
                                        {t('common.theme_light')}
                                    </span>
                                    {colorMode === 'light' ? <Check className="h-4 w-4 text-ink-accent" aria-hidden /> : null}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setColorMode('dark')}
                                    className={cn(
                                        'flex items-center justify-between gap-2 rounded-2xl border p-3.5 text-sm font-medium transition-colors',
                                        colorMode === 'dark'
                                            ? 'border-line-focus bg-secondary ring-1 ring-line-focus'
                                            : 'border-line hover:bg-secondary/50',
                                    )}
                                >
                                    <span className="inline-flex items-center gap-2">
                                        <Moon className="h-4 w-4" aria-hidden />
                                        {t('common.theme_dark')}
                                    </span>
                                    {colorMode === 'dark' ? <Check className="h-4 w-4 text-ink-accent" aria-hidden /> : null}
                                </button>
                            </div>

                            <div className="pt-2">
                                <Button type="submit" disabled={saving} className="w-full">
                                    <Save className="h-4 w-4" aria-hidden />
                                    {saving ? 'Saving…' : 'Save theme'}
                                </Button>
                            </div>
                        </CardBody>
                    </Card>
                </div>

                {/* Live Preview Swatch */}
                <Card className="h-fit">
                    <CardHeader
                        title={
                            <span className="inline-flex items-center gap-2">
                                <Palette className="h-4 w-4 text-ink-muted" aria-hidden />
                                Live preview
                            </span>
                        }
                        description="How your storefront cards, buttons, and typography will look."
                    />
                    <CardBody>
                        <div
                            className="space-y-4 border p-5 transition-all"
                            style={{
                                backgroundColor: colorMode === 'dark' ? '#0f172a' : '#ffffff',
                                color: colorMode === 'dark' ? '#f8fafc' : '#0f172a',
                                borderColor: colorMode === 'dark' ? '#334155' : '#e2e8f0',
                                borderRadius: cornerRadius === 'full' ? '1.5rem' : previewRadiusCss,
                                fontFamily: `'${fontBody}', system-ui`,
                            }}
                        >
                            <div className="flex items-center justify-between">
                                <span
                                    className="text-base font-bold"
                                    style={{ fontFamily: `'${fontHeading}', system-ui` }}
                                >
                                    Minivora Store
                                </span>
                                <span
                                    className="px-2.5 py-1 text-xs font-semibold text-white"
                                    style={{
                                        backgroundColor: accentColor,
                                        borderRadius: previewRadiusCss,
                                    }}
                                >
                                    New
                                </span>
                            </div>

                            <div
                                className="p-4"
                                style={{
                                    backgroundColor: colorMode === 'dark' ? '#1e293b' : '#f8fafc',
                                    borderRadius: cornerRadius === 'full' ? '1.25rem' : previewRadiusCss,
                                }}
                            >
                                <p
                                    className="text-lg font-semibold"
                                    style={{ fontFamily: `'${fontHeading}', system-ui` }}
                                >
                                    Summer Collection
                                </p>
                                <p
                                    className="mt-1 text-xs opacity-75"
                                    style={{ fontFamily: `'${fontBody}', system-ui` }}
                                >
                                    Handcrafted essentials delivered across Egypt.
                                </p>
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <span
                                        className="inline-flex items-center px-4 py-2 text-xs font-semibold text-white"
                                        style={{
                                            backgroundColor: primaryColor,
                                            borderRadius: previewRadiusCss,
                                        }}
                                    >
                                        Shop Now
                                    </span>
                                    <span
                                        className="inline-flex items-center border px-4 py-2 text-xs font-semibold"
                                        style={{
                                            borderColor: accentColor,
                                            color: accentColor,
                                            borderRadius: previewRadiusCss,
                                        }}
                                    >
                                        View Catalog
                                    </span>
                                </div>
                            </div>

                            <div className="flex items-center justify-between text-xs opacity-75">
                                <span dir="ltr">{primaryColor}</span>
                                <span dir="ltr">{accentColor}</span>
                                <span>{colorMode}</span>
                            </div>
                        </div>
                    </CardBody>
                </Card>
            </form>
        </DashboardLayout>
    );
}
