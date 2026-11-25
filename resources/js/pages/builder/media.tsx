// Media library: live file upload with EN/AR alt text and multi-select bulk delete.
import { router } from '@inertiajs/react';
import axios from 'axios';
import { Film, ImageIcon, Trash2, Upload } from 'lucide-react';
import { useRef, useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Checkbox, Field, Input } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';

interface MediaItem {
    id: number;
    disk: string;
    path: string;
    url?: string;
    mime_type: string;
    size_bytes: number;
    alt_en: string | null;
    alt_ar: string | null;
    created_at: string;
}

interface BuilderMediaProps {
    files?: MediaItem[];
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function BuilderMedia({ files = [] }: BuilderMediaProps) {
    const t = useT();
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [altEn, setAltEn] = useState('');
    const [altAr, setAltAr] = useState('');
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [busy, setBusy] = useState(false);
    const [notice, setNotice] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null);

    const toggleSelect = (id: number) => {
        setSelectedIds((prev) => (prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]));
    };

    const allSelected = files.length > 0 && selectedIds.length === files.length;

    const toggleSelectAll = () => {
        if (allSelected) {
            setSelectedIds([]);
        } else {
            setSelectedIds(files.map((f) => f.id));
        }
    };

    const handleUpload = async (e: FormEvent) => {
        e.preventDefault();
        if (!selectedFile) return;

        setBusy(true);
        setNotice(null);
        try {
            const formData = new FormData();
            formData.append('file', selectedFile);
            if (altEn.trim()) formData.append('alt_en', altEn.trim());
            if (altAr.trim()) formData.append('alt_ar', altAr.trim());

            await axios.post('/builder/media', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            setSelectedFile(null);
            setAltEn('');
            setAltAr('');
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
            setNotice({ tone: 'success', text: 'File uploaded to media library.' });
            router.reload();
        } catch (err) {
            const msg = axios.isAxiosError(err)
                ? ((err.response?.data as { message?: string })?.message ?? 'Upload failed.')
                : 'Upload failed.';
            setNotice({ tone: 'danger', text: msg });
        } finally {
            setBusy(false);
        }
    };

    const handleBulkDelete = async () => {
        if (selectedIds.length === 0) return;

        setBusy(true);
        setNotice(null);
        try {
            await axios.delete('/builder/media/bulk', {
                data: { ids: selectedIds },
            });
            const count = selectedIds.length;
            setSelectedIds([]);
            setNotice({ tone: 'success', text: `Deleted ${count} file(s).` });
            router.reload();
        } catch (err) {
            const msg = axios.isAxiosError(err)
                ? ((err.response?.data as { message?: string })?.message ?? 'Could not delete selected files.')
                : 'Could not delete selected files.';
            setNotice({ tone: 'danger', text: msg });
        } finally {
            setBusy(false);
        }
    };

    return (
        <DashboardLayout title={t('nav.builder_media')}>
            <PageHeader
                title={t('media.title')}
                description={t('media.subtitle')}
                action={
                    selectedIds.length > 0 ? (
                        <Button variant="danger" disabled={busy} onClick={handleBulkDelete}>
                            <Trash2 className="h-4 w-4" aria-hidden />
                            Delete selected ({selectedIds.length})
                        </Button>
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

            {/* Upload Card */}
            <Card className="mb-6">
                <CardHeader title={t('media.upload')} description={t('media.library_hint')} />
                <CardBody>
                    <form onSubmit={handleUpload} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1.2fr_1fr_1fr_auto] lg:items-end">
                        <Field label="File (image or video)" htmlFor="media-file">
                            <input
                                ref={fileInputRef}
                                id="media-file"
                                type="file"
                                accept="image/*,video/*"
                                onChange={(e) => setSelectedFile(e.target.files?.[0] ?? null)}
                                className="block w-full rounded-lg border border-line-control bg-elevated px-3 py-2 text-sm text-ink file:me-3 file:rounded-full file:border-0 file:bg-secondary file:px-3 file:py-1 file:text-xs file:font-semibold file:text-ink"
                                required
                            />
                        </Field>
                        <Field label="Alt text (EN)" htmlFor="media-alt-en">
                            <Input
                                id="media-alt-en"
                                value={altEn}
                                onChange={(e) => setAltEn(e.target.value)}
                                placeholder="Leather tote bag in Cairo"
                            />
                        </Field>
                        <Field label="Alt text (AR)" htmlFor="media-alt-ar">
                            <Input
                                id="media-alt-ar"
                                value={altAr}
                                onChange={(e) => setAltAr(e.target.value)}
                                placeholder="حقيبة جلدية يدوية"
                                dir="rtl"
                            />
                        </Field>
                        <Button type="submit" disabled={busy || !selectedFile}>
                            <Upload className="h-4 w-4" aria-hidden />
                            {busy ? 'Uploading…' : t('media.upload')}
                        </Button>
                    </form>
                </CardBody>
            </Card>

            {/* Media Grid */}
            <Card>
                <CardHeader
                    title={`${t('media.library')} (${files.length})`}
                    description="Select one or more assets to delete in bulk."
                    action={
                        files.length > 0 ? (
                            <Checkbox label={allSelected ? 'Clear selection' : 'Select all'} checked={allSelected} onChange={toggleSelectAll} />
                        ) : undefined
                    }
                />
                <CardBody>
                    {files.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-line p-10 text-center text-sm text-ink-muted">
                            <ImageIcon className="mx-auto mb-2 h-7 w-7 opacity-60" aria-hidden />
                            No media files uploaded yet. Upload your first image or video above.
                        </div>
                    ) : (
                        <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                            {files.map((file) => {
                                const checked = selectedIds.includes(file.id);
                                const isImage = file.mime_type.startsWith('image/');
                                const isVideo = file.mime_type.startsWith('video/');
                                const hasAlt = Boolean(file.alt_en?.trim() || file.alt_ar?.trim());
                                const filename = file.path.split('/').pop() ?? file.path;

                                return (
                                    <li
                                        key={file.id}
                                        className={cn(
                                            'group relative overflow-hidden rounded-2xl border transition-colors',
                                            checked ? 'border-line-focus ring-1 ring-line-focus' : 'border-line',
                                        )}
                                    >
                                        <div className="relative aspect-square bg-secondary">
                                            {isImage && file.url ? (
                                                <img
                                                    src={file.url}
                                                    alt={file.alt_en || file.alt_ar || filename}
                                                    className="h-full w-full object-cover"
                                                    loading="lazy"
                                                />
                                            ) : (
                                                <div className="grid h-full w-full place-items-center text-ink-muted">
                                                    {isVideo ? (
                                                        <Film className="h-8 w-8" strokeWidth={1.5} aria-hidden />
                                                    ) : (
                                                        <ImageIcon className="h-8 w-8" strokeWidth={1.5} aria-hidden />
                                                    )}
                                                </div>
                                            )}

                                            <div className="absolute start-2 top-2">
                                                <Checkbox
                                                    label=""
                                                    aria-label={`Select ${filename}`}
                                                    checked={checked}
                                                    onChange={() => toggleSelect(file.id)}
                                                />
                                            </div>

                                            {!hasAlt ? (
                                                <Badge tone="warning" className="absolute bottom-2 start-2 h-6 text-xs">
                                                    {t('media.no_alt')}
                                                </Badge>
                                            ) : null}
                                        </div>

                                        <div className="space-y-1 p-2.5 text-xs">
                                            <p className="truncate font-medium text-ink" title={filename}>
                                                {file.alt_en || file.alt_ar || filename}
                                            </p>
                                            <p dir="ltr" className="text-ink-muted">
                                                {formatBytes(file.size_bytes)} · {file.mime_type.split('/')[1] ?? file.mime_type}
                                            </p>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </CardBody>
            </Card>
        </DashboardLayout>
    );
}
