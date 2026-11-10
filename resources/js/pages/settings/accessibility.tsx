// Accessibility and display: theme, text size, motion, transparency, contrast, link underlines.
// Saved on this device and applied at once (lib/display-prefs.ts). NFR-USE-04 (WCAG 2.2 AA), NFR-USE-05.
import { Link } from '@inertiajs/react';
import { Contrast, Languages, Layers, Monitor, Moon, MoveRight, Sun, Type, Underline, type LucideIcon } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { Card } from '@/components/ui/card';
import { SettingsLayout } from '@/layouts/settings-layout';
import { cn } from '@/lib/cn';
import { defaultPrefs, readPrefs, savePrefs, type DisplayPrefs } from '@/lib/display-prefs';
import { useLocale, useT } from '@/lib/i18n';
import { stopSmoothScroll } from '@/lib/smooth-scroll';

interface Option<V extends string> {
    value: V;
    label: string;
    icon?: LucideIcon;
    sample?: ReactNode;
}

/** A set of large radio cards. Real radio inputs, so arrow keys and screen readers work as expected. */
function ChoiceGroup<V extends string>({ name, legend, hint, value, options, onChange }: { name: string; legend: string; hint?: string; value: V; options: Option<V>[]; onChange: (v: V) => void }) {
    return (
        <fieldset>
            <legend className="text-sm font-semibold">{legend}</legend>
            {hint ? <p className="mt-0.5 text-sm text-ink-muted">{hint}</p> : null}
            <div className={cn('mt-3 grid gap-2', options.length === 3 ? 'grid-cols-3' : 'grid-cols-2')}>
                {options.map((o) => {
                    const Icon = o.icon;
                    const id = `${name}-${o.value}`;
                    return (
                        <div key={o.value} className="relative">
                            <input type="radio" id={id} name={name} value={o.value} checked={value === o.value} onChange={() => onChange(o.value)} className="peer sr-only" />
                            <label
                                htmlFor={id}
                                className="flex min-h-[64px] cursor-pointer flex-col items-center justify-center gap-1.5 rounded-2xl border border-line bg-elevated px-2 py-3 text-center text-sm transition-colors duration-200 hover:border-line-strong peer-checked:border-line-focus peer-checked:bg-accent-subtle peer-checked:ring-1 peer-checked:ring-line-focus peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-line-focus"
                            >
                                {o.sample ?? (Icon ? <Icon className="h-5 w-5" aria-hidden strokeWidth={1.75} /> : null)}
                                {o.label}
                            </label>
                        </div>
                    );
                })}
            </div>
        </fieldset>
    );
}

/** On/off row with a real switch: role="switch" + aria-checked, full-row hit area. */
function SwitchRow({ id, icon: Icon, label, hint, checked, onChange, flipRtl = false }: { id: string; icon: LucideIcon; label: string; hint: string; checked: boolean; onChange: (v: boolean) => void; flipRtl?: boolean }) {
    return (
        <div className="flex items-center gap-3 py-3">
            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-accent-subtle text-ink-accent">
                <Icon className={cn('h-5 w-5', flipRtl && 'rtl:-scale-x-100')} aria-hidden strokeWidth={1.75} />
            </span>
            <div className="min-w-0 flex-1">
                <p id={`${id}-label`} className="text-sm font-medium">
                    {label}
                </p>
                <p id={`${id}-hint`} className="text-caption text-ink-muted">
                    {hint}
                </p>
            </div>
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                aria-labelledby={`${id}-label`}
                aria-describedby={`${id}-hint`}
                onClick={() => onChange(!checked)}
                className={cn('relative inline-flex h-8 w-[52px] shrink-0 items-center rounded-full transition-colors duration-200', checked ? 'bg-brand-accent' : 'bg-chart-track')}
            >
                <span className={cn('inline-block h-6 w-6 rounded-full bg-white shadow-sm transition-transform duration-200', checked ? 'translate-x-6 rtl:-translate-x-6' : 'translate-x-1 rtl:-translate-x-1')} />
            </button>
        </div>
    );
}

export default function AccessibilitySettings() {
    const t = useT();
    const locale = useLocale();
    const [prefs, setPrefs] = useState<DisplayPrefs>(defaultPrefs);
    const [announce, setAnnounce] = useState('');

    useEffect(() => setPrefs(readPrefs()), []);

    const update = <K extends keyof DisplayPrefs>(key: K, value: DisplayPrefs[K]) => {
        const next = { ...prefs, [key]: value };
        setPrefs(next);
        savePrefs(next);
        if (key === 'motion' && value === 'reduce') stopSmoothScroll();
        setAnnounce(t('a11y.saved'));
    };

    const reset = () => {
        setPrefs(defaultPrefs);
        savePrefs(defaultPrefs);
        setAnnounce(t('a11y.reset_done'));
    };

    return (
        <SettingsLayout title={t('a11y.title')} description={t('a11y.subtitle')}>
            <p role="status" aria-live="polite" className="sr-only">
                {announce}
            </p>

            <Card className="space-y-6 p-5 sm:p-6">
                <ChoiceGroup
                    name="theme"
                    legend={t('a11y.theme')}
                    value={prefs.theme}
                    onChange={(v) => update('theme', v)}
                    options={[
                        { value: 'system', label: t('a11y.theme_system'), icon: Monitor },
                        { value: 'light', label: t('common.theme_light'), icon: Sun },
                        { value: 'dark', label: t('common.theme_dark'), icon: Moon },
                    ]}
                />
                <ChoiceGroup
                    name="text-size"
                    legend={t('a11y.text_size')}
                    hint={t('a11y.text_size_hint')}
                    value={prefs.textSize}
                    onChange={(v) => update('textSize', v)}
                    options={[
                        { value: 'default', label: t('a11y.size_default'), sample: <span className="font-display text-base leading-none" aria-hidden>{locale === 'ar' ? 'أ' : 'Aa'}</span> },
                        { value: 'large', label: t('a11y.size_large'), sample: <span className="font-display text-lg leading-none" aria-hidden>{locale === 'ar' ? 'أ' : 'Aa'}</span> },
                        { value: 'larger', label: t('a11y.size_larger'), sample: <span className="font-display text-xl leading-none" aria-hidden>{locale === 'ar' ? 'أ' : 'Aa'}</span> },
                    ]}
                />
            </Card>

            <Card className="p-5 sm:p-6">
                <h2 className="font-display text-base">{t('a11y.comfort')}</h2>
                <p className="mt-1 text-sm text-ink-muted">{t('a11y.comfort_hint')}</p>
                <div className="mt-2 divide-y divide-line">
                    <SwitchRow id="motion" icon={MoveRight} flipRtl label={t('a11y.motion')} hint={t('a11y.motion_hint')} checked={prefs.motion === 'reduce'} onChange={(on) => update('motion', on ? 'reduce' : 'system')} />
                    <SwitchRow id="transparency" icon={Layers} label={t('a11y.transparency')} hint={t('a11y.transparency_hint')} checked={prefs.transparency === 'reduce'} onChange={(on) => update('transparency', on ? 'reduce' : 'system')} />
                    <SwitchRow id="contrast" icon={Contrast} label={t('a11y.contrast')} hint={t('a11y.contrast_hint')} checked={prefs.contrast === 'more'} onChange={(on) => update('contrast', on ? 'more' : 'system')} />
                    <SwitchRow id="links" icon={Underline} label={t('a11y.links')} hint={t('a11y.links_hint')} checked={prefs.underlineLinks} onChange={(on) => update('underlineLinks', on)} />
                </div>
            </Card>

            <Card className="flex flex-wrap items-center gap-4 p-5 sm:p-6">
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-accent-subtle text-ink-accent">
                    <Languages className="h-5 w-5" aria-hidden strokeWidth={1.75} />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium">{t('a11y.language')}</p>
                    <p className="text-caption text-ink-muted">{t('a11y.language_hint')}</p>
                </div>
                <Link href="/settings/account" className="inline-flex h-10 items-center rounded-full px-4 text-sm font-semibold text-ink-link hover:bg-secondary">
                    {t('a11y.language_action')}
                </Link>
            </Card>

            <div className="flex flex-wrap items-center justify-between gap-3 px-1">
                <p className="flex items-center gap-2 text-caption text-ink-muted">
                    <Type className="h-4 w-4" aria-hidden />
                    {t('a11y.device_note')}
                </p>
                <button type="button" onClick={reset} className="inline-flex h-10 items-center rounded-full px-4 text-sm font-semibold text-ink-link hover:bg-secondary">
                    {t('a11y.reset')}
                </button>
            </div>
        </SettingsLayout>
    );
}
