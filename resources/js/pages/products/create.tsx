// Add product, step 1 (Figma "Add product · Step 1 · HIG"): drop photos, describe it, fill it in
// yourself, or import a CSV. Every path creates a draft and opens the review step.
import { Link, router } from '@inertiajs/react';
import { ChevronLeft, SendHorizontal, Sparkles, Upload } from 'lucide-react';
import { useRef, useState, type DragEvent, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';

export default function CreateProduct({ maxPhotos }: { maxPhotos: number }) {
    const t = useT();
    const [over, setOver] = useState(false);
    const [busy, setBusy] = useState(false);
    const [note, setNote] = useState('');
    const [error, setError] = useState<string | null>(null);
    const photoInput = useRef<HTMLInputElement>(null);
    const csvInput = useRef<HTMLInputElement>(null);

    const done = () => setBusy(false);
    const failed = (errors: Record<string, string>) => setError(Object.values(errors)[0] ?? null);

    const sendPhotos = (files: FileList | null) => {
        const photos = Array.from(files ?? []).filter((f) => f.type.startsWith('image/'));
        if (photos.length === 0) return;
        if (photos.length > maxPhotos) {
            setError(t('create_product.too_many', { max: maxPhotos }));
            return;
        }
        setBusy(true);
        setError(null);
        router.post('/products', { photos }, { forceFormData: true, onFinish: done, onError: failed });
    };

    const onDrop = (e: DragEvent) => {
        e.preventDefault();
        setOver(false);
        sendPhotos(e.dataTransfer.files);
    };

    const sendNote = (e: FormEvent) => {
        e.preventDefault();
        if (!note.trim()) return;
        setBusy(true);
        router.post('/products', { note }, { onFinish: done, onError: failed });
    };

    const blank = () => {
        setBusy(true);
        router.post('/products', {}, { onFinish: done, onError: failed });
    };

    const sendCsv = (files: FileList | null) => {
        const csv = files?.[0];
        if (!csv) return;
        setBusy(true);
        router.post('/products/import', { csv }, { forceFormData: true, onFinish: done, onError: failed });
    };

    return (
        <DashboardLayout title={t('create_product.title')}>
            <Link href="/products" className="inline-flex items-center gap-1 text-sm text-ink-muted hover:text-ink">
                <ChevronLeft className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
                {t('nav.products')}
            </Link>

            <div className="mx-auto mt-4 flex max-w-[550px] flex-col items-center text-center" aria-busy={busy}>
                <div className="orb h-[72px] w-[72px] rounded-full sm:h-24 sm:w-24" aria-hidden />
                <h1 className="mt-8 font-display text-[28px] font-light leading-tight tracking-[-0.02em]">{t('create_product.title')}</h1>
                <p className="mt-2 text-sm text-ink-muted">{t('create_product.subtitle')}</p>

                <button
                    type="button"
                    onClick={() => photoInput.current?.click()}
                    onDragOver={(e) => {
                        e.preventDefault();
                        setOver(true);
                    }}
                    onDragLeave={() => setOver(false)}
                    onDrop={onDrop}
                    disabled={busy}
                    className={cn(
                        'card mt-6 flex w-full flex-col items-center gap-3 border-2 border-dashed !border-line-control px-6 py-10 transition-colors',
                        over && '!border-line-focus bg-accent-subtle',
                    )}
                >
                    <span className="grid h-12 w-12 place-items-center rounded-full bg-primary text-primary-fg shadow-md">
                        <Upload className="h-5 w-5" aria-hidden />
                    </span>
                    <span className="font-display text-xl font-light">{t('create_product.drop')}</span>
                    <span className="text-caption text-ink-muted">{t('create_product.drop_hint', { max: maxPhotos })}</span>
                </button>
                <input ref={photoInput} type="file" accept="image/*" multiple className="sr-only" tabIndex={-1} aria-hidden onChange={(e) => sendPhotos(e.target.files)} />

                <div className="my-5 flex w-full items-center gap-3 text-caption text-ink-muted">
                    <span className="h-px flex-1 bg-line" />
                    {t('create_product.or_tell')}
                    <span className="h-px flex-1 bg-line" />
                </div>

                <form onSubmit={sendNote} className="w-full">
                    <label htmlFor="note" className="sr-only">
                        {t('create_product.note_label')}
                    </label>
                    <div className="flex h-12 items-center gap-2 rounded-full bg-elevated pe-1 ps-4 shadow-md">
                        <Sparkles className="h-[18px] w-[18px] shrink-0 text-brand-accent" aria-hidden />
                        <input
                            id="note"
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            placeholder={t('create_product.note_ph')}
                            maxLength={500}
                            autoComplete="off"
                            className="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-sm placeholder:text-ink-muted focus:ring-0"
                        />
                        <button type="submit" disabled={busy || !note.trim()} className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary text-primary-fg hover:bg-primary-hover disabled:opacity-60" aria-label={t('create_product.note_send')}>
                            <SendHorizontal className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
                        </button>
                    </div>
                </form>

                <div className="mt-4 flex flex-wrap justify-center gap-2">
                    <Button variant="glass" size="sm" className="h-9 px-4" onClick={blank} disabled={busy}>
                        {t('create_product.manual')}
                    </Button>
                    <Button variant="glass" size="sm" className="h-9 px-4" onClick={() => csvInput.current?.click()} disabled={busy}>
                        <Upload className="h-4 w-4" aria-hidden />
                        {t('create_product.csv')}
                    </Button>
                    <input ref={csvInput} type="file" accept=".csv,text/csv" className="sr-only" tabIndex={-1} aria-hidden onChange={(e) => sendCsv(e.target.files)} />
                </div>
                <p className="mt-3 text-caption text-ink-muted">{t('create_product.csv_hint')}</p>

                <p role="alert" className={cn('mt-4 text-sm text-danger', !error && 'sr-only')}>
                    {error}
                </p>
            </div>
        </DashboardLayout>
    );
}
