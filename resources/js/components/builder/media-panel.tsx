// Media library as a panel inside the store editor: upload (button or drop), browse, copy links,
// delete, and put a picture straight into the selected section's or block's image field.
import axios from 'axios';
import { Check, Copy, Film, ImagePlus, Loader2, Trash2, Upload, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState, type DragEvent } from 'react';
import { cn } from '@/lib/cn';
import type { MediaFileRef } from './types';

export interface MediaTarget {
    key: string;
    label: string;
    kind: 'image' | 'video';
}

interface Props {
    isAr: boolean;
    onClose: () => void;
    flash: (text: string, tone?: 'ok' | 'err') => void;
    /** Image/video fields of the current selection; empty when nothing is selected. */
    targets: MediaTarget[];
    targetName: string | null;
    onUse: (url: string, fieldKey: string) => void;
}

const errorText = (e: unknown) =>
    axios.isAxiosError(e) ? ((e.response?.data as { message?: string } | undefined)?.message ?? e.message) : String(e);

export function MediaPanel({ isAr, onClose, flash, targets, targetName, onUse }: Props) {
    const L = (en: string, ar: string) => (isAr ? ar : en);
    const [files, setFiles] = useState<MediaFileRef[] | null>(null);
    const [filter, setFilter] = useState<'all' | 'image' | 'video'>('all');
    const [picked, setPicked] = useState<number[]>([]);
    const [active, setActive] = useState<MediaFileRef | null>(null);
    const [uploading, setUploading] = useState(0);
    const [dragging, setDragging] = useState(false);
    const [copied, setCopied] = useState(false);
    const input = useRef<HTMLInputElement>(null);

    const refresh = useCallback(async () => {
        const { data } = await axios.get<{ files: MediaFileRef[] }>('/builder/media/list');
        setFiles(data.files);
        return data.files;
    }, []);

    useEffect(() => {
        refresh().catch(() => setFiles([]));
    }, [refresh]);

    const upload = async (list: FileList | File[] | null) => {
        const chosen = Array.from(list ?? []).filter((f) => f.type.startsWith('image/') || f.type.startsWith('video/'));
        if (!chosen.length) return;
        setUploading(chosen.length);
        let failed = 0;
        for (const file of chosen) {
            try {
                const body = new FormData();
                body.append('file', file);
                await axios.post('/builder/media', body);
            } catch (e) {
                failed++;
                flash(`${file.name}: ${errorText(e)}`, 'err');
            }
            setUploading((n) => n - 1);
        }
        const fresh = await refresh().catch(() => null);
        if (fresh && failed < chosen.length) {
            flash(L(`Uploaded ${chosen.length - failed} file(s)`, `تم رفع ${chosen.length - failed} ملف`));
            setActive(fresh[0] ?? null);
        }
    };

    const remove = async (ids: number[]) => {
        if (!ids.length || !window.confirm(L(`Delete ${ids.length} file(s)? Pages using them will show an empty picture.`, `حذف ${ids.length} ملف؟ الصفحات التي تستخدمها ستظهر بدون صورة.`))) return;
        try {
            await axios.delete('/builder/media/bulk', { data: { ids } });
            setPicked([]);
            if (active && ids.includes(active.id)) setActive(null);
            await refresh();
            flash(L('Deleted', 'تم الحذف'));
        } catch (e) {
            flash(errorText(e), 'err');
        }
    };

    const copy = async (url: string) => {
        const absolute = new URL(url, window.location.origin).href;
        try {
            await navigator.clipboard.writeText(absolute);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            window.prompt(L('Copy this link', 'انسخ هذا الرابط'), absolute);
        }
    };

    const onDrop = (e: DragEvent) => {
        e.preventDefault();
        setDragging(false);
        void upload(e.dataTransfer.files);
    };

    const shown = (files ?? []).filter((f) => filter === 'all' || f.mime_type.startsWith(filter));
    const activeKind = active?.mime_type.startsWith('video') ? 'video' : 'image';
    const usable = active ? targets.filter((t) => t.kind === activeKind) : [];

    return (
        <div className="flex min-h-0 flex-1 flex-col" onDragOver={(e) => { e.preventDefault(); setDragging(true); }} onDragLeave={() => setDragging(false)} onDrop={onDrop}>
            <div className="flex items-start gap-2 border-b border-line px-4 py-3">
                <div className="min-w-0 flex-1 pt-1">
                    <h2 className="font-display text-[0.9375rem]">{L('Media library', 'مكتبة الوسائط')}</h2>
                    <p className="mt-0.5 text-xs text-ink-muted">{L('Drop pictures or videos here, or upload.', 'اسحب الصور أو الفيديو هنا، أو ارفعها.')}</p>
                </div>
                <button type="button" onClick={onClose} aria-label={L('Close', 'إغلاق')} className="grid h-9 w-9 place-items-center rounded-full text-ink-muted hover:bg-secondary hover:text-ink"><X className="h-4 w-4" /></button>
            </div>

            <div className="flex items-center gap-2 border-b border-line px-4 py-2.5">
                <input ref={input} type="file" multiple accept="image/*,video/*" className="hidden" onChange={(e) => { void upload(e.target.files); e.target.value = ''; }} />
                <button type="button" onClick={() => input.current?.click()} disabled={uploading > 0} className="inline-flex h-8 items-center gap-1.5 rounded-full bg-primary px-3 text-xs font-semibold text-primary-fg disabled:opacity-60">
                    {uploading > 0 ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Upload className="h-3.5 w-3.5" />}
                    {uploading > 0 ? L(`Uploading ${uploading}…`, `جارٍ رفع ${uploading}…`) : L('Upload', 'رفع')}
                </button>
                <div className="ms-auto flex rounded-full bg-secondary p-0.5 text-[0.6875rem] font-semibold">
                    {(['all', 'image', 'video'] as const).map((k) => (
                        <button key={k} type="button" aria-pressed={filter === k} onClick={() => setFilter(k)} className={cn('rounded-full px-2 py-1', filter === k ? 'bg-surface text-ink shadow-sm' : 'text-ink-muted')}>
                            {k === 'all' ? L('All', 'الكل') : k === 'image' ? L('Images', 'صور') : L('Videos', 'فيديو')}
                        </button>
                    ))}
                </div>
            </div>

            <div className={cn('relative min-h-0 flex-1 overflow-y-auto p-3', dragging && 'bg-accent-subtle')}>
                {dragging ? (
                    <div className="pointer-events-none absolute inset-3 z-10 grid place-items-center rounded-lg border-2 border-dashed border-line-focus text-sm font-semibold text-ink-accent">
                        {L('Drop to upload', 'أفلت للرفع')}
                    </div>
                ) : null}
                {files === null ? <div className="grid place-items-center py-10"><Loader2 className="h-5 w-5 animate-spin text-ink-muted" /></div> : null}
                {files !== null && shown.length === 0 ? (
                    <button type="button" onClick={() => input.current?.click()} className="flex w-full flex-col items-center gap-2 rounded-lg border-2 border-dashed border-line px-4 py-10 text-center text-sm text-ink-muted hover:border-line-focus">
                        <ImagePlus className="h-6 w-6" />
                        {L('No files yet. Drop or click to upload.', 'لا توجد ملفات بعد. اسحب أو اضغط للرفع.')}
                    </button>
                ) : null}
                <div className="grid grid-cols-3 gap-2">
                    {shown.map((f) => {
                        const isPicked = picked.includes(f.id);
                        const video = f.mime_type.startsWith('video');
                        return (
                            <div key={f.id} className={cn('group relative aspect-square overflow-hidden rounded-md border bg-secondary', active?.id === f.id ? 'border-line-focus ring-2 ring-line-focus' : 'border-line')}>
                                <button type="button" onClick={() => setActive(f)} className="block h-full w-full" aria-label={f.alt_en || L('File', 'ملف')}>
                                    {video ? (
                                        <span className="grid h-full w-full place-items-center text-ink-muted"><Film className="h-6 w-6" /></span>
                                    ) : (
                                        <img src={f.url} alt={f.alt_en ?? ''} loading="lazy" className="h-full w-full object-cover" />
                                    )}
                                </button>
                                <label className={cn('absolute start-1 top-1 grid h-5 w-5 cursor-pointer place-items-center rounded border bg-surface/90', isPicked ? 'border-primary' : 'border-line opacity-0 group-hover:opacity-100')}>
                                    <input type="checkbox" className="sr-only" checked={isPicked} onChange={() => setPicked((p) => (isPicked ? p.filter((x) => x !== f.id) : [...p, f.id]))} aria-label={L('Select', 'تحديد')} />
                                    {isPicked ? <Check className="h-3.5 w-3.5 text-ink" /> : null}
                                </label>
                            </div>
                        );
                    })}
                </div>
            </div>

            {picked.length > 0 ? (
                <div className="flex items-center justify-between gap-2 border-t border-line px-4 py-2.5 text-xs">
                    <span className="font-semibold">{L(`${picked.length} selected`, `${picked.length} محدد`)}</span>
                    <div className="flex gap-2">
                        <button type="button" onClick={() => setPicked([])} className="rounded-full px-2.5 py-1 font-semibold hover:bg-secondary">{L('Clear', 'إلغاء')}</button>
                        <button type="button" onClick={() => remove(picked)} className="inline-flex items-center gap-1 rounded-full bg-danger-subtle px-2.5 py-1 font-semibold text-danger"><Trash2 className="h-3.5 w-3.5" />{L('Delete', 'حذف')}</button>
                    </div>
                </div>
            ) : active ? (
                <div className="flex flex-col gap-2 border-t border-line px-4 py-3">
                    <div className="flex items-center gap-3">
                        <div className="h-12 w-12 shrink-0 overflow-hidden rounded-md bg-secondary">
                            {activeKind === 'image' ? <img src={active.url} alt="" className="h-full w-full object-cover" /> : <span className="grid h-full w-full place-items-center text-ink-muted"><Film className="h-5 w-5" /></span>}
                        </div>
                        <p className="min-w-0 flex-1 truncate font-mono text-[0.6875rem] text-ink-muted" dir="ltr">{active.url.split('/').pop()}</p>
                        <button type="button" onClick={() => copy(active.url)} title={L('Copy link', 'نسخ الرابط')} aria-label={L('Copy link', 'نسخ الرابط')} className="grid h-8 w-8 place-items-center rounded-full hover:bg-secondary">{copied ? <Check className="h-4 w-4 text-success" /> : <Copy className="h-4 w-4" />}</button>
                        <button type="button" onClick={() => remove([active.id])} title={L('Delete', 'حذف')} aria-label={L('Delete', 'حذف')} className="grid h-8 w-8 place-items-center rounded-full text-danger hover:bg-danger-subtle"><Trash2 className="h-4 w-4" /></button>
                    </div>
                    {usable.length > 0 ? (
                        <div className="flex flex-col gap-1">
                            <p className="text-[0.6875rem] text-ink-muted">{L('Use for', 'استخدمها في')} {targetName}:</p>
                            <div className="flex flex-wrap gap-1.5">
                                {usable.map((t) => (
                                    <button key={t.key} type="button" onClick={() => onUse(active.url, t.key)} className="rounded-full border border-line px-2.5 py-1 text-xs font-semibold hover:border-line-focus hover:bg-secondary">{t.label}</button>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <p className="text-[0.6875rem] text-ink-muted">{targets.length ? L('The selected section has no field for this kind of file.', 'القسم المحدد ليس به حقل لهذا النوع.') : L('Select a section in the preview to put this picture into it.', 'اختر قسماً في المعاينة لوضع هذه الصورة فيه.')}</p>
                    )}
                </div>
            ) : null}
        </div>
    );
}
