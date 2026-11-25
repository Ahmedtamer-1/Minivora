// One settings field in the store editor, rendered from its schema type.
import axios from 'axios';
import { ImagePlus, Loader2, Search, Upload, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { cn } from '@/lib/cn';
import type { CatalogRefs, FieldDef, MediaFileRef, Settings } from './types';

export const inputCls =
    'w-full rounded-md border border-line-control bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-line-focus focus:outline-none focus:ring-1 focus:ring-line-focus';

export function FieldShell({ label, htmlFor, hint, children, inline }: { label: ReactNode; htmlFor?: string; hint?: ReactNode; children: ReactNode; inline?: boolean }) {
    return (
        <div className={cn('flex gap-1.5', inline ? 'flex-row items-center justify-between' : 'flex-col')}>
            <label htmlFor={htmlFor} className="text-xs font-semibold text-ink">
                {label}
            </label>
            {children}
            {hint ? <p className="text-[0.6875rem] leading-snug text-ink-muted">{hint}</p> : null}
        </div>
    );
}

interface FieldInputProps {
    field: FieldDef;
    settings: Settings;
    lang: 'en' | 'ar';
    onChange: (key: string, value: unknown) => void;
    catalog: CatalogRefs;
    idPrefix: string;
    isAr: boolean;
}

export function isFieldVisible(field: FieldDef, settings: Settings): boolean {
    if (!field.show_if) return true;
    return Object.entries(field.show_if).every(([k, v]) => String(settings[k] ?? '') === v);
}

export function FieldInput({ field, settings, lang, onChange, catalog, idPrefix, isAr }: FieldInputProps) {
    const key = field.tr && lang === 'ar' ? `${field.id}_ar` : field.id;
    const value = settings[key];
    const id = `${idPrefix}-${key}`;
    const label = (
        <>
            {isAr ? field.label_ar : field.label}
            {field.tr ? <span className="ms-1.5 rounded bg-secondary px-1 py-px font-mono text-[0.625rem] font-medium uppercase text-ink-muted">{lang}</span> : null}
        </>
    );
    const set = (v: unknown) => onChange(key, v);
    const str = value === null || value === undefined ? '' : String(value);
    const dir = field.tr ? (lang === 'ar' ? 'rtl' : 'ltr') : undefined;

    switch (field.type) {
        case 'text':
        case 'url':
            return (
                <FieldShell label={label} htmlFor={id} hint={field.type === 'url' ? (isAr ? 'رابط داخلي مثل /shop أو رابط كامل' : 'Store path like /shop or a full https:// link') : undefined}>
                    <input id={id} dir={dir} className={inputCls} value={str} onChange={(e) => set(e.target.value)} list={field.type === 'url' ? 'mv-link-suggestions' : undefined} />
                </FieldShell>
            );
        case 'textarea':
        case 'richtext':
        case 'links':
            return (
                <FieldShell
                    label={label}
                    htmlFor={id}
                    hint={
                        field.type === 'richtext'
                            ? isAr ? 'سطر جديد = فقرة. يدعم <b> و <i> و <a href>.' : 'New line = line break. Supports <b>, <i>, <a href>, <ul>.'
                            : field.type === 'links'
                              ? isAr ? 'سطر لكل رابط: النص | /الرابط' : 'One per line: Label | /link'
                              : undefined
                    }
                >
                    <textarea id={id} dir={dir} rows={field.type === 'textarea' ? 3 : 5} className={cn(inputCls, 'resize-y', field.type === 'links' && 'font-mono text-xs')} value={str} onChange={(e) => set(e.target.value)} />
                </FieldShell>
            );
        case 'code':
            return (
                <FieldShell label={label} htmlFor={id} hint={isAr ? 'تُحذف السكربتات ومعالجات الأحداث تلقائياً.' : 'Scripts and on* handlers are removed automatically.'}>
                    <textarea id={id} dir="ltr" spellCheck={false} rows={12} className={cn(inputCls, 'resize-y font-mono text-xs leading-relaxed')} value={str} onChange={(e) => set(e.target.value)} />
                </FieldShell>
            );
        case 'color':
            return (
                <FieldShell label={label} htmlFor={id}>
                    <div className="flex items-center gap-2">
                        <input type="color" aria-label={typeof field.label === 'string' ? field.label : undefined} className="h-9 w-11 shrink-0 cursor-pointer rounded-md border border-line-control bg-surface p-1" value={/^#[0-9a-f]{6}$/i.test(str) ? str : '#000000'} onChange={(e) => set(e.target.value)} />
                        <input id={id} dir="ltr" className={cn(inputCls, 'font-mono')} placeholder={isAr ? 'افتراضي' : 'Default'} value={str} onChange={(e) => set(e.target.value)} />
                        {str ? (
                            <button type="button" className="grid h-9 w-9 shrink-0 place-items-center rounded-md text-ink-muted hover:bg-secondary" onClick={() => set('')} aria-label={isAr ? 'مسح' : 'Clear'}>
                                <X className="h-4 w-4" />
                            </button>
                        ) : null}
                    </div>
                </FieldShell>
            );
        case 'select':
            return (
                <FieldShell label={label} htmlFor={id}>
                    <select id={id} className={inputCls} value={str} onChange={(e) => set(e.target.value)}>
                        {Object.entries(field.options ?? {}).map(([v, l]) => (
                            <option key={v} value={v}>
                                {l}
                            </option>
                        ))}
                    </select>
                </FieldShell>
            );
        case 'range': {
            const n = Number(value ?? field.default ?? field.min ?? 0);
            return (
                <FieldShell label={label} htmlFor={id}>
                    <div className="flex items-center gap-3">
                        <input id={id} type="range" className="w-full accent-[var(--action-primary)]" min={field.min} max={field.max} step={field.step ?? 1} value={n} onChange={(e) => set(Number(e.target.value))} />
                        <span className="w-16 shrink-0 text-end font-mono text-xs text-ink-muted">
                            {field.unit === 'ms' ? `${(n / 1000).toFixed(1)}s` : `${n}${field.unit ?? ''}`}
                        </span>
                    </div>
                </FieldShell>
            );
        }
        case 'number':
            return (
                <FieldShell label={label} htmlFor={id}>
                    <input id={id} type="number" className={inputCls} value={str} min={field.min} max={field.max} onChange={(e) => set(Number(e.target.value))} />
                </FieldShell>
            );
        case 'checkbox':
            return (
                <label className="flex cursor-pointer items-center justify-between gap-3 py-1 text-xs font-semibold text-ink">
                    <span>{isAr ? field.label_ar : field.label}</span>
                    <span className="relative inline-flex">
                        <input type="checkbox" className="peer sr-only" checked={Boolean(value)} onChange={(e) => set(e.target.checked)} />
                        <span className="h-5 w-9 rounded-full bg-secondary transition peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-line-focus" />
                        <span className="absolute start-0.5 top-0.5 h-4 w-4 rounded-full bg-surface shadow transition peer-checked:translate-x-4 rtl:peer-checked:-translate-x-4" />
                    </span>
                </label>
            );
        case 'datetime':
            return (
                <FieldShell label={label} htmlFor={id}>
                    <input id={id} type="datetime-local" className={inputCls} value={str.slice(0, 16)} onChange={(e) => set(e.target.value)} />
                </FieldShell>
            );
        case 'image':
        case 'video':
            return <MediaField id={id} label={label} kind={field.type} value={str} onChange={set} isAr={isAr} />;
        case 'product':
            return (
                <FieldShell label={label} htmlFor={id}>
                    <select id={id} className={inputCls} value={str} onChange={(e) => set(e.target.value ? Number(e.target.value) : null)}>
                        <option value="">{isAr ? '— اختر —' : '— Choose —'}</option>
                        {catalog.products.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </select>
                </FieldShell>
            );
        case 'category':
            return (
                <FieldShell label={label} htmlFor={id}>
                    <select id={id} className={inputCls} value={str} onChange={(e) => set(e.target.value ? Number(e.target.value) : null)}>
                        <option value="">{isAr ? '— اختر —' : '— Choose —'}</option>
                        {catalog.categories.map((c) => (
                            <option key={c.id} value={c.id}>
                                {isAr && c.name_ar ? c.name_ar : c.name}
                            </option>
                        ))}
                    </select>
                </FieldShell>
            );
        case 'product_list':
        case 'category_list': {
            const items = field.type === 'product_list' ? catalog.products.map((p) => ({ id: p.id, name: p.name })) : catalog.categories.map((c) => ({ id: c.id, name: isAr && c.name_ar ? c.name_ar : c.name }));
            return <MultiPick label={label} items={items} value={Array.isArray(value) ? (value as number[]) : []} onChange={set} isAr={isAr} />;
        }
        default:
            return null;
    }
}

function MultiPick({ label, items, value, onChange, isAr }: { label: ReactNode; items: { id: number; name: string }[]; value: number[]; onChange: (v: number[]) => void; isAr: boolean }) {
    const [q, setQ] = useState('');
    const chosen = value.map((id) => items.find((i) => i.id === id)).filter(Boolean) as { id: number; name: string }[];
    const options = items.filter((i) => !value.includes(i.id) && i.name.toLowerCase().includes(q.toLowerCase())).slice(0, 8);
    return (
        <FieldShell label={label}>
            {chosen.length > 0 ? (
                <ul className="flex flex-col gap-1">
                    {chosen.map((c, i) => (
                        <li key={c.id} className="flex items-center gap-2 rounded-md bg-secondary px-2 py-1 text-xs">
                            <span className="font-mono text-ink-muted">{i + 1}</span>
                            <span className="min-w-0 flex-1 truncate">{c.name}</span>
                            <button type="button" aria-label={isAr ? 'إزالة' : 'Remove'} className="text-ink-muted hover:text-ink" onClick={() => onChange(value.filter((v) => v !== c.id))}>
                                <X className="h-3.5 w-3.5" />
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
            <div className="relative">
                <Search className="pointer-events-none absolute start-2.5 top-2.5 h-4 w-4 text-ink-muted" />
                <input className={cn(inputCls, 'ps-8')} placeholder={isAr ? 'ابحث للإضافة…' : 'Search to add…'} value={q} onChange={(e) => setQ(e.target.value)} />
            </div>
            {q || items.length <= 8 ? (
                <div className="flex flex-col">
                    {options.map((o) => (
                        <button key={o.id} type="button" className="rounded px-2 py-1.5 text-start text-xs hover:bg-secondary" onClick={() => { onChange([...value, o.id]); setQ(''); }}>
                            + {o.name}
                        </button>
                    ))}
                    {options.length === 0 ? <p className="px-2 text-xs text-ink-muted">{isAr ? 'لا نتائج' : 'No matches'}</p> : null}
                </div>
            ) : null}
        </FieldShell>
    );
}

let mediaCache: MediaFileRef[] | null = null;

function MediaField({ id, label, kind, value, onChange, isAr }: { id: string; label: ReactNode; kind: 'image' | 'video'; value: string; onChange: (v: string) => void; isAr: boolean }) {
    const [open, setOpen] = useState(false);
    return (
        <FieldShell label={label} htmlFor={id}>
            <div className="flex flex-col gap-2">
                {value && kind === 'image' ? (
                    <div className="relative overflow-hidden rounded-md border border-line bg-secondary">
                        <img src={value} alt="" className="h-28 w-full object-cover" />
                        <button type="button" onClick={() => onChange('')} className="absolute end-1.5 top-1.5 grid h-7 w-7 place-items-center rounded-full bg-surface/90 text-ink shadow" aria-label={isAr ? 'إزالة' : 'Remove'}>
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                ) : null}
                <div className="flex gap-2">
                    <input id={id} dir="ltr" className={cn(inputCls, 'min-w-0 text-xs')} placeholder={kind === 'video' ? 'https://youtube.com/… or .mp4' : 'https://…'} value={value} onChange={(e) => onChange(e.target.value)} />
                    <button type="button" onClick={() => setOpen(true)} className="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-line-control px-2.5 text-xs font-semibold hover:bg-secondary">
                        <ImagePlus className="h-4 w-4" /> {isAr ? 'المكتبة' : 'Library'}
                    </button>
                </div>
            </div>
            {open ? <MediaPicker kind={kind} isAr={isAr} onClose={() => setOpen(false)} onPick={(url) => { onChange(url); setOpen(false); }} /> : null}
        </FieldShell>
    );
}

export function MediaPicker({ kind, isAr, onClose, onPick }: { kind: 'image' | 'video'; isAr: boolean; onClose: () => void; onPick: (url: string) => void }) {
    const [files, setFiles] = useState<MediaFileRef[] | null>(mediaCache);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const input = useRef<HTMLInputElement>(null);

    useEffect(() => {
        axios.get<{ files: MediaFileRef[] }>('/builder/media/list').then(({ data }) => {
            mediaCache = data.files;
            setFiles(data.files);
        }).catch(() => setFiles([]));
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onClose]);

    const shown = useMemo(() => (files ?? []).filter((f) => f.mime_type.startsWith(kind)), [files, kind]);

    const upload = async (list: FileList | null) => {
        if (!list?.length) return;
        setUploading(true);
        setError(null);
        try {
            for (const file of Array.from(list)) {
                const body = new FormData();
                body.append('file', file);
                const { data } = await axios.post<{ file: MediaFileRef & { url?: string } }>('/builder/media', body);
                const fresh = await axios.get<{ files: MediaFileRef[] }>('/builder/media/list');
                mediaCache = fresh.data.files;
                setFiles(fresh.data.files);
                if (list.length === 1) {
                    const url = data.file.url ?? fresh.data.files.find((f) => f.id === data.file.id)?.url;
                    if (url) onPick(url);
                }
            }
        } catch (e) {
            setError(axios.isAxiosError(e) ? ((e.response?.data as { message?: string })?.message ?? e.message) : String(e));
        } finally {
            setUploading(false);
        }
    };

    return (
        <div className="fixed inset-0 z-[80] grid place-items-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-label={isAr ? 'مكتبة الوسائط' : 'Media library'} onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
            <div className="flex max-h-[80vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-line bg-elevated shadow-lg">
                <div className="flex items-center justify-between gap-3 border-b border-line px-5 py-3">
                    <h2 className="font-display text-base">{isAr ? 'مكتبة الوسائط' : 'Media library'}</h2>
                    <div className="flex items-center gap-2">
                        <input ref={input} type="file" accept={kind === 'image' ? 'image/*' : 'video/*'} multiple className="hidden" onChange={(e) => upload(e.target.files)} />
                        <button type="button" disabled={uploading} onClick={() => input.current?.click()} className="inline-flex h-9 items-center gap-2 rounded-full bg-primary px-4 text-sm font-semibold text-primary-fg disabled:opacity-60">
                            {uploading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />} {isAr ? 'رفع' : 'Upload'}
                        </button>
                        <button type="button" onClick={onClose} className="grid h-9 w-9 place-items-center rounded-full hover:bg-secondary" aria-label={isAr ? 'إغلاق' : 'Close'}>
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                </div>
                {error ? <p className="bg-danger-subtle px-5 py-2 text-sm text-danger">{error}</p> : null}
                <div className="grid flex-1 grid-cols-3 gap-3 overflow-y-auto p-5 sm:grid-cols-4 md:grid-cols-5">
                    {files === null ? <p className="col-span-full text-sm text-ink-muted">{isAr ? 'جارٍ التحميل…' : 'Loading…'}</p> : null}
                    {files !== null && shown.length === 0 ? <p className="col-span-full py-10 text-center text-sm text-ink-muted">{isAr ? 'لا توجد ملفات بعد. ارفع ملفاً.' : 'No files yet — upload one.'}</p> : null}
                    {shown.map((f) => (
                        <button key={f.id} type="button" onClick={() => onPick(f.url)} className="group overflow-hidden rounded-lg border border-line bg-secondary text-start focus:outline-none focus:ring-2 focus:ring-line-focus">
                            {kind === 'image' ? <img src={f.url} alt={f.alt_en ?? ''} className="aspect-square w-full object-cover transition group-hover:scale-105" loading="lazy" /> : <video src={f.url} className="aspect-square w-full object-cover" muted preload="metadata" />}
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}
