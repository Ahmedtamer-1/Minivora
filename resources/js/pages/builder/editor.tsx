// Store editor: Shopify-style three panes. Left: header, page and footer sections with their blocks
// (drag to reorder). Centre: live draft preview of the real storefront. Right: settings for the
// selected section or block, its style, and its own CSS. Changes autosave as a draft; Publish makes
// them live and keeps a restorable version. FR-BLD-01, FR-BLD-02, FR-BLD-03, FR-BLD-06, FR-BLD-07
import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    ArrowLeft, ChevronDown, ChevronRight, Code2, Copy, Eye, EyeOff, FilePlus2, GripVertical, History, Home, Layers, LayoutTemplate,
    Loader2, Monitor, MoreHorizontal, Paintbrush, Plus, Redo2, RotateCcw, Search, Settings2, Smartphone, Sparkles, Trash2, Undo2, UploadCloud, X,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState, type DragEvent, type ReactNode } from 'react';
import { FieldInput, FieldShell, inputCls, isFieldVisible } from '@/components/builder/field-input';
import {
    blockDefaults, blockDefs, blocksOf, deepClone, uid,
    type Block, type CatalogRefs, type EditorSection, type FieldDef, type PageSummary, type SectionDef, type Settings, type Version,
} from '@/components/builder/types';
import { cn } from '@/lib/cn';
import { useLocale } from '@/lib/i18n';

interface ThemeProps {
    primary_color: string;
    accent_color: string;
    font_heading: string;
    font_body: string;
    corner_radius: string;
    color_mode: string;
    custom_css: string | null;
    settings: Record<string, unknown>;
}

interface Props {
    pages: PageSummary[];
    groups: { header: PageSummary; footer: PageSummary };
    initialPageId: number | null;
    sectionTypes: Record<string, SectionDef>;
    styleFields: FieldDef[];
    templates: string[];
    theme: ThemeProps;
    storeUrl: string;
    catalog: CatalogRefs;
}

type GroupKey = 'header' | 'template' | 'footer';
interface Selection { group: GroupKey; key: string; blockId?: string }
type SaveState = 'saved' | 'dirty' | 'saving' | 'error';

const categoryLabels: Record<string, [string, string]> = {
    hero: ['Banners', 'البانرات'], catalog: ['Products & categories', 'المنتجات والفئات'], content: ['Text & story', 'النصوص والقصة'],
    media: ['Images', 'الصور'], video: ['Video', 'الفيديو'], social: ['Social proof', 'آراء ومتابعة'], advanced: ['Advanced', 'متقدم'],
    header: ['Header', 'رأس الصفحة'], footer: ['Footer', 'التذييل'],
};

const toEditor = (s: { id: number | null; type: string; is_visible: boolean; settings: Settings }): EditorSection => ({ key: s.id ? `s${s.id}` : uid(), id: s.id, type: s.type, is_visible: s.is_visible, settings: s.settings ?? {} });
const toPayload = (list: EditorSection[]) => list.map((s) => ({ id: s.id, type: s.type, is_visible: s.is_visible, settings: s.settings }));
const errorText = (e: unknown) => {
    if (axios.isAxiosError(e)) {
        const data = e.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;
        return (data?.errors && Object.values(data.errors).flat()[0]) || data?.message || e.message;
    }
    return String(e);
};

export default function BuilderEditor({ pages: initialPages, groups, initialPageId, sectionTypes, styleFields, templates, theme: initialTheme, storeUrl, catalog }: Props) {
    const uiLocale = useLocale();
    const isAr = uiLocale === 'ar';
    const L = (en: string, ar: string) => (isAr ? ar : en);

    const [pages, setPages] = useState(initialPages);
    const [pageId, setPageId] = useState<number | null>(initialPageId);
    const page = pages.find((p) => p.id === pageId) ?? null;

    const groupPageId: Record<GroupKey, number | null> = { header: groups.header.id, template: pageId, footer: groups.footer.id };
    const [docs, setDocs] = useState<Record<GroupKey, EditorSection[] | null>>({ header: null, template: null, footer: null });
    const [versions, setVersions] = useState<Version[]>([]);
    const [selection, setSelection] = useState<Selection | null>(null);
    const [tab, setTab] = useState<'content' | 'style' | 'css'>('content');
    const [lang, setLang] = useState<'en' | 'ar'>(uiLocale);
    const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
    const [save, setSave] = useState<SaveState>('saved');
    const [toast, setToast] = useState<{ tone: 'ok' | 'err'; text: string } | null>(null);
    const [picker, setPicker] = useState<GroupKey | null>(null);
    const [blockPicker, setBlockPicker] = useState<Selection | null>(null);
    const [panel, setPanel] = useState<'page' | 'theme' | 'versions' | null>(null);
    const [newPage, setNewPage] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [frameKey, setFrameKey] = useState(0);
    const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});

    const frame = useRef<HTMLIFrameElement>(null);
    const saveTimers = useRef<Partial<Record<GroupKey, number>>>({});
    const renderTimers = useRef<Record<string, number>>({});
    const history = useRef<{ past: string[]; future: string[] }>({ past: [], future: [] });
    const lastSnapshot = useRef<string>('');
    const docsRef = useRef(docs);
    docsRef.current = docs;

    const flash = useCallback((text: string, tone: 'ok' | 'err' = 'ok') => {
        setToast({ text, tone });
        window.setTimeout(() => setToast(null), tone === 'err' ? 6000 : 2600);
    }, []);

    /* ── Loading ─────────────────────────────────────── */
    const load = useCallback(async (group: GroupKey, id: number) => {
        const { data } = await axios.get<{ page: PageSummary & { sections: EditorSection[]; versions: Version[] } }>(`/builder/pages/${id}`);
        setDocs((d) => ({ ...d, [group]: data.page.sections.map(toEditor) }));
        if (group === 'template') {
            setVersions(data.page.versions);
            setPages((ps) => ps.map((p) => (p.id === id ? { ...p, ...data.page } : p)));
        }
    }, []);

    useEffect(() => {
        void load('header', groups.header.id);
        void load('footer', groups.footer.id);
    }, [groups.header.id, groups.footer.id, load]);

    useEffect(() => {
        if (pageId === null) return;
        setDocs((d) => ({ ...d, template: null }));
        setSelection(null);
        history.current = { past: [], future: [] };
        lastSnapshot.current = '';
        void load('template', pageId);
        const url = new URL(window.location.href);
        url.searchParams.set('page', String(pageId));
        window.history.replaceState(window.history.state, '', url);
    }, [pageId, load]);

    /* ── Saving & preview ───────────────────────────── */
    const persist = useCallback(async (group: GroupKey, reload: boolean) => {
        const id = groupPageId[group];
        const list = docsRef.current[group];
        if (id === null || list === null) return;
        setSave('saving');
        try {
            const { data } = await axios.put<{ sections: { id: number; type: string; is_visible: boolean; settings: Settings }[]; page: PageSummary }>(`/builder/pages/${id}/sections`, { sections: toPayload(list) });
            // Attach server ids to new sections, keeping client keys stable.
            setDocs((d) => {
                const current = d[group];
                if (!current) return d;
                return { ...d, [group]: current.map((s, i) => (s.id === null && data.sections[i] ? { ...s, id: data.sections[i].id } : s)) };
            });
            if (group === 'template') setPages((ps) => ps.map((p) => (p.id === id ? { ...p, ...data.page } : p)));
            else setPages((ps) => [...ps]);
            setSave('saved');
            if (reload) setFrameKey((k) => k + 1);
        } catch (e) {
            setSave('error');
            flash(errorText(e), 'err');
        }
    }, [groupPageId.header, groupPageId.template, groupPageId.footer, flash]); // eslint-disable-line react-hooks/exhaustive-deps

    const scheduleSave = useCallback((group: GroupKey, delay = 700) => {
        setSave('dirty');
        window.clearTimeout(saveTimers.current[group]);
        saveTimers.current[group] = window.setTimeout(() => void persist(group, false), delay);
    }, [persist]);

    const postToFrame = (msg: Record<string, unknown>) => frame.current?.contentWindow?.postMessage({ source: 'mv-editor', ...msg }, window.location.origin);

    const livePreview = useCallback((section: EditorSection) => {
        if (section.id === null) return;
        window.clearTimeout(renderTimers.current[section.key]);
        renderTimers.current[section.key] = window.setTimeout(async () => {
            try {
                const { data } = await axios.post<{ html: string }>('/builder/render-section', {
                    locale: lang,
                    template: page?.is_home ? 'home' : 'page',
                    section: { id: section.id, type: section.type, is_visible: section.is_visible, settings: section.settings },
                });
                postToFrame({ type: 'replace', id: String(section.id), html: data.html });
            } catch { /* the full reload after saving will catch up */ }
        }, 180);
    }, [lang, page?.is_home]);

    // Undo/redo over all three groups.
    const snapshot = () => JSON.stringify(docsRef.current);
    const remember = () => {
        const snap = snapshot();
        if (snap === lastSnapshot.current) return;
        if (lastSnapshot.current) history.current.past.push(lastSnapshot.current);
        if (history.current.past.length > 80) history.current.past.shift();
        history.current.future = [];
        lastSnapshot.current = snap;
    };
    useEffect(() => {
        if (docs.header && docs.footer && docs.template && !lastSnapshot.current) lastSnapshot.current = JSON.stringify(docs);
    }, [docs]);

    const restoreSnapshot = (snap: string) => {
        const next = JSON.parse(snap) as typeof docs;
        setDocs(next);
        docsRef.current = next;
        lastSnapshot.current = snap;
        (['header', 'template', 'footer'] as GroupKey[]).forEach((g) => void persist(g, g === 'footer'));
    };
    const undo = () => {
        const prev = history.current.past.pop();
        if (!prev) return;
        history.current.future.push(snapshot());
        restoreSnapshot(prev);
    };
    const redo = () => {
        const next = history.current.future.pop();
        if (!next) return;
        history.current.past.push(snapshot());
        restoreSnapshot(next);
    };

    /** Apply a change to one group's sections. structural = order/add/remove → save now and reload the preview. */
    const mutate = (group: GroupKey, fn: (list: EditorSection[]) => EditorSection[], opts: { structural?: boolean; changed?: string } = {}) => {
        const list = docsRef.current[group];
        if (!list) return;
        const next = fn(deepClone(list));
        const updated = { ...docsRef.current, [group]: next };
        docsRef.current = updated;
        setDocs(updated);
        if (opts.structural) {
            window.clearTimeout(saveTimers.current[group]);
            void persist(group, true);
        } else {
            scheduleSave(group);
            const changed = next.find((s) => s.key === opts.changed);
            if (changed) livePreview(changed);
        }
        window.clearTimeout(renderTimers.current.__history);
        renderTimers.current.__history = window.setTimeout(remember, 400);
    };

    // Messages from the preview frame.
    useEffect(() => {
        const onMessage = (e: MessageEvent) => {
            if (e.origin !== window.location.origin || e.data?.source !== 'mv-preview') return;
            if (e.data.type === 'select') {
                const id = String(e.data.id);
                for (const g of ['header', 'template', 'footer'] as GroupKey[]) {
                    const found = docsRef.current[g]?.find((s) => String(s.id) === id);
                    if (found) {
                        setSelection({ group: g, key: found.key });
                        setTab('content');
                        return;
                    }
                }
            }
            if (e.data.type === 'ready' && selectionRef.current) {
                const s = findSection(selectionRef.current);
                if (s?.id) postToFrame({ type: 'select', id: String(s.id) });
            }
        };
        window.addEventListener('message', onMessage);
        return () => window.removeEventListener('message', onMessage);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const selectionRef = useRef(selection);
    selectionRef.current = selection;
    const findSection = (sel: Selection | null) => (sel ? docsRef.current[sel.group]?.find((s) => s.key === sel.key) ?? null : null);

    const select = (sel: Selection | null) => {
        setSelection(sel);
        setPanel(null);
        setTab('content');
        const s = findSection(sel);
        if (s?.id) postToFrame({ type: 'select', id: String(s.id) });
    };

    // Keyboard shortcuts.
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            const typing = (e.target as HTMLElement)?.closest('input, textarea, select, [contenteditable]');
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'z' && !typing) {
                e.preventDefault();
                if (e.shiftKey) redo();
                else undo();
            }
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                (['header', 'template', 'footer'] as GroupKey[]).forEach((g) => void persist(g, false));
            }
            if (e.key === 'Escape' && !typing) setSelection(null);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    });

    // Warn before leaving with unsaved changes.
    useEffect(() => {
        const onUnload = (e: BeforeUnloadEvent) => {
            if (save === 'dirty' || save === 'saving') e.preventDefault();
        };
        window.addEventListener('beforeunload', onUnload);
        return () => window.removeEventListener('beforeunload', onUnload);
    }, [save]);

    /* ── Section & block operations ─────────────────── */
    const addSection = (group: GroupKey, type: string) => {
        const def = sectionTypes[type];
        const section: EditorSection = { key: uid(), id: null, type, is_visible: true, settings: deepClone(def.defaults) };
        const selected = selection?.group === group ? selection.key : null;
        mutate(group, (list) => {
            const at = selected ? list.findIndex((s) => s.key === selected) + 1 : list.length;
            list.splice(at > 0 ? at : list.length, 0, section);
            return list;
        }, { structural: true });
        setPicker(null);
        setSelection({ group, key: section.key });
        setTab('content');
    };

    const updateSection = (sel: Selection, fn: (s: EditorSection) => void) => {
        mutate(sel.group, (list) => {
            const s = list.find((x) => x.key === sel.key);
            if (s) fn(s);
            return list;
        }, { changed: sel.key });
    };

    const setSetting = (sel: Selection, key: string, value: unknown) => updateSection(sel, (s) => { s.settings[key] = value; });
    const setStyle = (sel: Selection, key: string, value: unknown) => updateSection(sel, (s) => {
        const style = (s.settings.style && typeof s.settings.style === 'object' ? s.settings.style : {}) as Settings;
        style[key] = value;
        s.settings.style = style;
    });
    const setBlock = (sel: Selection, blockId: string, key: string, value: unknown) => updateSection(sel, (s) => {
        const b = blocksOf(s.settings).find((x) => x.id === blockId);
        if (b) b.settings[key] = value;
    });

    const removeSection = (sel: Selection) => {
        mutate(sel.group, (list) => list.filter((s) => s.key !== sel.key), { structural: true });
        setSelection(null);
    };
    const duplicateSection = (sel: Selection) => {
        let copyKey = '';
        mutate(sel.group, (list) => {
            const i = list.findIndex((s) => s.key === sel.key);
            const copy = { ...deepClone(list[i]), key: uid(), id: null };
            copy.settings.blocks = blocksOf(copy.settings).map((b) => ({ ...b, id: uid() }));
            copyKey = copy.key;
            list.splice(i + 1, 0, copy);
            return list;
        }, { structural: true });
        setSelection({ group: sel.group, key: copyKey });
    };
    const toggleSection = (sel: Selection) => mutate(sel.group, (list) => list.map((s) => (s.key === sel.key ? { ...s, is_visible: !s.is_visible } : s)), { structural: true });
    const moveSection = (group: GroupKey, from: number, to: number) => mutate(group, (list) => {
        if (to < 0 || to >= list.length) return list;
        const [item] = list.splice(from, 1);
        list.splice(to, 0, item);
        return list;
    }, { structural: true });

    const openBlockPicker = (sel: Selection) => {
        const s = findSection(sel);
        const keys = s ? Object.keys(blockDefs(sectionTypes[s.type])) : [];
        if (keys.length === 1) addBlock(sel, keys[0]);
        else setBlockPicker(sel);
    };

    const addBlock = (sel: Selection, type: string) => {
        const s = findSection(sel);
        if (!s) return;
        const def = blockDefs(sectionTypes[s.type])[type];
        const block: Block = { id: uid(), type, hidden: false, settings: blockDefaults(def) };
        mutate(sel.group, (list) => {
            const target = list.find((x) => x.key === sel.key);
            if (target) target.settings.blocks = [...blocksOf(target.settings), block];
            return list;
        }, { changed: sel.key });
        setBlockPicker(null);
        setSelection({ ...sel, blockId: block.id });
    };
    const blockOp = (sel: Selection, blockId: string, op: 'remove' | 'duplicate' | 'toggle' | 'up' | 'down') => {
        mutate(sel.group, (list) => {
            const target = list.find((x) => x.key === sel.key);
            if (!target) return list;
            const blocks = blocksOf(target.settings);
            const i = blocks.findIndex((b) => b.id === blockId);
            if (i < 0) return list;
            if (op === 'remove') blocks.splice(i, 1);
            if (op === 'duplicate') blocks.splice(i + 1, 0, { ...deepClone(blocks[i]), id: uid() });
            if (op === 'toggle') blocks[i].hidden = !blocks[i].hidden;
            if ((op === 'up' && i > 0) || (op === 'down' && i < blocks.length - 1)) {
                const j = op === 'up' ? i - 1 : i + 1;
                [blocks[i], blocks[j]] = [blocks[j], blocks[i]];
            }
            target.settings.blocks = blocks;
            return list;
        }, { changed: sel.key });
        if (op === 'remove') setSelection({ group: sel.group, key: sel.key });
    };
    const moveBlock = (sel: Selection, from: number, to: number) => mutate(sel.group, (list) => {
        const target = list.find((x) => x.key === sel.key);
        if (!target) return list;
        const blocks = blocksOf(target.settings);
        const [item] = blocks.splice(from, 1);
        blocks.splice(to, 0, item);
        target.settings.blocks = blocks;
        return list;
    }, { changed: sel.key });

    /* ── Drag & drop ─────────────────────────────────── */
    const drag = useRef<{ kind: 'section' | 'block'; group: GroupKey; index: number; sectionKey?: string } | null>(null);
    const [dropHint, setDropHint] = useState<string | null>(null);
    const onDragStart = (data: NonNullable<typeof drag.current>) => (e: DragEvent) => {
        drag.current = data;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', 'mv');
    };
    const onDragOver = (hint: string, accepts: (d: NonNullable<typeof drag.current>) => boolean) => (e: DragEvent) => {
        if (drag.current && accepts(drag.current)) {
            e.preventDefault();
            setDropHint(hint);
        }
    };
    const onDrop = (fn: (d: NonNullable<typeof drag.current>) => void) => (e: DragEvent) => {
        e.preventDefault();
        if (drag.current) fn(drag.current);
        drag.current = null;
        setDropHint(null);
    };

    /* ── Publish, pages, theme ──────────────────────── */
    const flushAll = async () => {
        (['header', 'template', 'footer'] as GroupKey[]).forEach((g) => window.clearTimeout(saveTimers.current[g]));
        await Promise.all((['header', 'template', 'footer'] as GroupKey[]).map((g) => persist(g, false)));
    };
    const publish = async () => {
        if (!page) return;
        setPublishing(true);
        try {
            await flushAll();
            await axios.post(`/builder/pages/${page.id}/publish`);
            await load('template', page.id);
            flash(L('Published — your changes are live.', 'تم النشر — تغييراتك ظاهرة الآن.'));
        } catch (e) {
            flash(errorText(e), 'err');
        } finally {
            setPublishing(false);
        }
    };
    const restore = async (v: Version) => {
        if (!page || !window.confirm(L(`Restore version ${v.version_number}? Your current draft is replaced.`, `استعادة الإصدار ${v.version_number}؟ سيتم استبدال المسودة الحالية.`))) return;
        try {
            await axios.post(`/builder/pages/${page.id}/versions/${v.id}/restore`);
            await load('template', page.id);
            setFrameKey((k) => k + 1);
            flash(L('Version restored as your draft.', 'تمت استعادة الإصدار كمسودة.'));
        } catch (e) {
            flash(errorText(e), 'err');
        }
    };

    const selected = findSection(selection);
    const selectedDef = selected ? sectionTypes[selected.type] : undefined;
    const selectedBlock = selected && selection?.blockId ? blocksOf(selected.settings).find((b) => b.id === selection.blockId) : undefined;
    const selectedBlockDef = selectedBlock && selectedDef ? blockDefs(selectedDef)[selectedBlock.type] : undefined;

    const previewSrc = page ? `/builder/preview/${page.id}?locale=${lang}` : null;
    const liveUrl = page ? storeUrl.replace(/\/(ar|en)$/, `/${lang}`) + (page.is_home ? '' : `/pages/${page.slug}`) : storeUrl;
    const anyChanges = pages.find((p) => p.id === pageId)?.has_changes;

    const label = (type: string) => (sectionTypes[type] ? (isAr ? sectionTypes[type].label_ar : sectionTypes[type].label) : type);
    const sectionTitle = (s: EditorSection) => {
        const name = ['heading', 'headline', 'title'].map((k) => s.settings[lang === 'ar' ? `${k}_ar` : k] || s.settings[k]).find((v) => typeof v === 'string' && v.trim());
        return name ? String(name) : '';
    };
    const blockTitle = (s: EditorSection, b: Block) => {
        const def = blockDefs(sectionTypes[s.type])[b.type];
        const text = ['heading', 'title', 'text', 'label', 'question', 'quote', 'author', 'caption', 'alt'].map((k) => b.settings[lang === 'ar' ? `${k}_ar` : k] || b.settings[k]).find((v) => typeof v === 'string' && v.trim());
        return { kind: def ? (isAr ? def.label_ar : def.label) : b.type, text: text ? String(text).replace(/<[^>]+>/g, '').slice(0, 40) : '' };
    };

    /* ── Render ──────────────────────────────────────── */
    const renderGroup = (group: GroupKey, title: string) => {
        const list = docs[group];
        return (
            <div className="border-b border-line py-2">
                <p className="px-4 pb-1 pt-2 text-label uppercase text-ink-muted">{title}</p>
                {list === null ? <div className="px-4 py-2"><Loader2 className="h-4 w-4 animate-spin text-ink-muted" /></div> : null}
                <ul>
                    {list?.map((s, i) => {
                        const sel: Selection = { group, key: s.key };
                        const isSel = selection?.group === group && selection.key === s.key;
                        const def = sectionTypes[s.type];
                        const defsB = blockDefs(def);
                        const hasBlocks = Object.keys(defsB).length > 0;
                        const open = hasBlocks && (collapsed[s.key] === false || (collapsed[s.key] === undefined && isSel));
                        const blocks = blocksOf(s.settings);
                        return (
                            <li key={s.key}
                                onDragOver={onDragOver(`${group}:${i}`, (d) => d.kind === 'section' && d.group === group)}
                                onDragLeave={() => setDropHint(null)}
                                onDrop={onDrop((d) => moveSection(group, d.index, i))}
                                className={cn(dropHint === `${group}:${i}` && 'shadow-[inset_0_2px_0_var(--border-focus)]')}>
                                <div className={cn('group/row flex items-center gap-1 pe-2 ps-1 text-sm', isSel && !selection?.blockId ? 'bg-accent-subtle text-ink' : 'hover:bg-secondary', !s.is_visible && 'opacity-50')}>
                                    <span draggable onDragStart={onDragStart({ kind: 'section', group, index: i })} className="grid h-8 w-6 cursor-grab place-items-center text-ink-muted opacity-0 group-hover/row:opacity-100" aria-hidden><GripVertical className="h-3.5 w-3.5" /></span>
                                    <button type="button" aria-label={open ? L('Collapse', 'طي') : L('Expand', 'توسيع')} disabled={!hasBlocks} onClick={() => setCollapsed((c) => ({ ...c, [s.key]: open }))} className={cn('grid h-6 w-5 place-items-center text-ink-muted', !hasBlocks && 'invisible')}>
                                        {open ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5 rtl:rotate-180" />}
                                    </button>
                                    <button type="button" className="flex min-w-0 flex-1 flex-col py-1.5 text-start" onClick={() => select(sel)}>
                                        <span className="truncate font-medium">{label(s.type)}</span>
                                        {sectionTitle(s) ? <span className="truncate text-[0.6875rem] text-ink-muted">{sectionTitle(s)}</span> : null}
                                    </button>
                                    <div className="flex items-center opacity-0 group-hover/row:opacity-100 focus-within:opacity-100">
                                        <IconBtn label={L('Move up', 'لأعلى')} onClick={() => moveSection(group, i, i - 1)} disabled={i === 0}><ChevronDown className="h-3.5 w-3.5 rotate-180" /></IconBtn>
                                        <IconBtn label={s.is_visible ? L('Hide', 'إخفاء') : L('Show', 'إظهار')} onClick={() => toggleSection(sel)}>{s.is_visible ? <Eye className="h-3.5 w-3.5" /> : <EyeOff className="h-3.5 w-3.5" />}</IconBtn>
                                    </div>
                                </div>
                                {open ? (
                                    <ul className="pb-1">
                                        {blocks.map((b, bi) => {
                                            const bt = blockTitle(s, b);
                                            const bSel = isSel && selection?.blockId === b.id;
                                            return (
                                                <li key={b.id}
                                                    onDragOver={onDragOver(`${s.key}:${bi}`, (d) => d.kind === 'block' && d.sectionKey === s.key)}
                                                    onDragLeave={() => setDropHint(null)}
                                                    onDrop={onDrop((d) => moveBlock(sel, d.index, bi))}
                                                    className={cn('group/b flex items-center gap-1 pe-2 ps-9 text-[0.8125rem]', bSel ? 'bg-accent-subtle' : 'hover:bg-secondary', b.hidden && 'opacity-50', dropHint === `${s.key}:${bi}` && 'shadow-[inset_0_2px_0_var(--border-focus)]')}>
                                                    <span draggable onDragStart={onDragStart({ kind: 'block', group, index: bi, sectionKey: s.key })} className="grid h-7 w-5 cursor-grab place-items-center text-ink-muted opacity-0 group-hover/b:opacity-100" aria-hidden><GripVertical className="h-3 w-3" /></span>
                                                    <button type="button" className="min-w-0 flex-1 truncate py-1.5 text-start" onClick={() => select({ ...sel, blockId: b.id })}>
                                                        <span className="text-ink-muted">{bt.kind}</span>{bt.text ? <span> · {bt.text}</span> : null}
                                                    </button>
                                                    <IconBtn className="opacity-0 group-hover/b:opacity-100" label={b.hidden ? L('Show', 'إظهار') : L('Hide', 'إخفاء')} onClick={() => blockOp(sel, b.id, 'toggle')}>{b.hidden ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}</IconBtn>
                                                </li>
                                            );
                                        })}
                                        {(def?.max_blocks ?? 0) > blocks.length ? (
                                            <li>
                                                <button type="button" onClick={() => openBlockPicker(sel)} className="flex w-full items-center gap-1.5 py-1.5 pe-2 ps-10 text-[0.8125rem] font-medium text-ink-link hover:bg-secondary">
                                                    <Plus className="h-3.5 w-3.5" /> {L('Add block', 'إضافة كتلة')}
                                                </button>
                                            </li>
                                        ) : null}
                                    </ul>
                                ) : null}
                            </li>
                        );
                    })}
                </ul>
                <button type="button" onClick={() => setPicker(group)} className="mx-2 mt-1 flex w-[calc(100%-1rem)] items-center gap-2 rounded-md px-2 py-2 text-sm font-medium text-ink-link hover:bg-secondary">
                    <Plus className="h-4 w-4" /> {L('Add section', 'إضافة قسم')}
                </button>
            </div>
        );
    };

    return (
        <>
            <Head title={L('Store editor', 'محرر المتجر')} />
            <datalist id="mv-link-suggestions">
                <option value="/" /><option value="/shop" /><option value="/cart" /><option value="/search" />
                {pages.filter((p) => !p.is_home).map((p) => <option key={p.id} value={`/pages/${p.slug}`} />)}
                {catalog.categories.map((c) => <option key={c.id} value={`/categories/${encodeURIComponent(c.name.toLowerCase().replace(/\s+/g, '-'))}`} />)}
            </datalist>
            <div className="fixed inset-0 flex flex-col bg-canvas text-ink" dir={isAr ? 'rtl' : 'ltr'}>
                {/* Top bar */}
                <header className="flex h-14 shrink-0 items-center gap-2 border-b border-line bg-surface px-3">
                    <a href="/" className="grid h-9 w-9 place-items-center rounded-full hover:bg-secondary" aria-label={L('Back to dashboard', 'العودة للوحة التحكم')}><ArrowLeft className="h-4 w-4 rtl:rotate-180" /></a>
                    <PageSwitcher pages={pages} page={page} isAr={isAr} onPick={(id) => setPageId(id)} onNew={() => setNewPage(true)} />
                    <div className="mx-auto hidden items-center gap-1 rounded-full bg-secondary p-1 md:flex">
                        <Seg active={device === 'desktop'} onClick={() => setDevice('desktop')} label={L('Desktop', 'سطح المكتب')}><Monitor className="h-4 w-4" /></Seg>
                        <Seg active={device === 'mobile'} onClick={() => setDevice('mobile')} label={L('Mobile', 'الجوال')}><Smartphone className="h-4 w-4" /></Seg>
                        <span className="mx-1 h-5 w-px bg-line" />
                        <Seg active={lang === 'en'} onClick={() => { setLang('en'); setFrameKey((k) => k + 1); }} label="English"><span className="px-1 text-xs font-bold">EN</span></Seg>
                        <Seg active={lang === 'ar'} onClick={() => { setLang('ar'); setFrameKey((k) => k + 1); }} label="العربية"><span className="px-1 text-xs font-bold">ع</span></Seg>
                    </div>
                    <div className="ms-auto flex items-center gap-1 md:ms-0">
                        <span className="hidden items-center gap-1.5 px-2 text-xs text-ink-muted lg:flex" aria-live="polite">
                            {save === 'saving' ? <><Loader2 className="h-3.5 w-3.5 animate-spin" />{L('Saving…', 'جارٍ الحفظ…')}</> : save === 'dirty' ? L('Unsaved', 'غير محفوظ') : save === 'error' ? <span className="text-danger">{L('Not saved', 'لم يُحفظ')}</span> : L('Draft saved', 'تم حفظ المسودة')}
                        </span>
                        <IconBtn label={L('Undo (Ctrl+Z)', 'تراجع')} onClick={undo} size="md"><Undo2 className="h-4 w-4" /></IconBtn>
                        <IconBtn label={L('Redo (Ctrl+Shift+Z)', 'إعادة')} onClick={redo} size="md"><Redo2 className="h-4 w-4" /></IconBtn>
                        <IconBtn label={L('Theme & custom CSS', 'المظهر و CSS')} onClick={() => { setSelection(null); setPanel('theme'); }} size="md" active={panel === 'theme'}><Paintbrush className="h-4 w-4" /></IconBtn>
                        <IconBtn label={L('Version history', 'سجل الإصدارات')} onClick={() => { setSelection(null); setPanel('versions'); }} size="md" active={panel === 'versions'}><History className="h-4 w-4" /></IconBtn>
                        <a href={liveUrl} target="_blank" rel="noopener" className="hidden h-9 items-center gap-1.5 rounded-full px-3 text-sm font-semibold hover:bg-secondary sm:inline-flex"><Eye className="h-4 w-4" />{L('View live', 'عرض المتجر')}</a>
                        <button type="button" onClick={publish} disabled={publishing || !page} className="inline-flex h-9 items-center gap-2 rounded-full bg-primary px-4 text-sm font-semibold text-primary-fg hover:bg-primary-hover disabled:opacity-60">
                            {publishing ? <Loader2 className="h-4 w-4 animate-spin" /> : <UploadCloud className="h-4 w-4" />}
                            {L('Publish', 'نشر')}{anyChanges ? <span className="h-1.5 w-1.5 rounded-full bg-warning" aria-label={L('unpublished changes', 'تغييرات غير منشورة')} /> : null}
                        </button>
                    </div>
                </header>

                <div className="flex min-h-0 flex-1">
                    {/* Left: section tree */}
                    <aside className="flex w-72 shrink-0 flex-col overflow-y-auto border-e border-line bg-surface" aria-label={L('Sections', 'الأقسام')}>
                        <button type="button" onClick={() => { setSelection(null); setPanel('page'); }} className={cn('flex items-center gap-2 border-b border-line px-4 py-3 text-start text-sm', panel === 'page' ? 'bg-accent-subtle' : 'hover:bg-secondary')}>
                            {page?.is_home ? <Home className="h-4 w-4 text-ink-muted" /> : <LayoutTemplate className="h-4 w-4 text-ink-muted" />}
                            <span className="min-w-0 flex-1 truncate font-semibold">{page ? (isAr && page.title_ar ? page.title_ar : page.title) : L('No page', 'لا توجد صفحة')}</span>
                            <Settings2 className="h-4 w-4 text-ink-muted" />
                        </button>
                        {renderGroup('header', L('Header', 'رأس الصفحة'))}
                        {renderGroup('template', L('Page', 'الصفحة'))}
                        {renderGroup('footer', L('Footer', 'التذييل'))}
                        <p className="px-4 py-4 text-[0.6875rem] leading-relaxed text-ink-muted">{L('Tip: click anything in the preview to edit it. Drag ⋮⋮ to reorder. Ctrl+Z undoes.', 'نصيحة: اضغط على أي جزء في المعاينة لتعديله. اسحب ⋮⋮ لإعادة الترتيب. Ctrl+Z للتراجع.')}</p>
                    </aside>

                    {/* Centre: preview */}
                    <main className="relative flex min-w-0 flex-1 items-stretch justify-center overflow-auto bg-[color-mix(in_srgb,var(--text-primary)_6%,transparent)] p-3">
                        {previewSrc ? (
                            <iframe
                                key={`${previewSrc}-${frameKey}`}
                                ref={frame}
                                src={previewSrc}
                                title={L('Store preview', 'معاينة المتجر')}
                                className={cn('h-full rounded-lg border border-line bg-white shadow-md transition-[width] duration-300', device === 'mobile' ? 'w-[390px]' : 'w-full')}
                            />
                        ) : (
                            <div className="grid place-items-center text-sm text-ink-muted">{L('Create a page to start.', 'أنشئ صفحة للبدء.')}</div>
                        )}
                        {toast ? (
                            <div role="status" className={cn('absolute bottom-6 left-1/2 -translate-x-1/2 rounded-full px-4 py-2 text-sm font-medium shadow-lg', toast.tone === 'ok' ? 'bg-ink text-canvas' : 'bg-danger text-white')}>{toast.text}</div>
                        ) : null}
                    </main>

                    {/* Right: settings */}
                    <aside className="flex w-80 shrink-0 flex-col overflow-hidden border-s border-line bg-surface" aria-label={L('Settings', 'الإعدادات')}>
                        {selected && selectedDef && selection ? (
                            selectedBlock && selectedBlockDef ? (
                                <SettingsPanel
                                    title={isAr ? selectedBlockDef.label_ar : selectedBlockDef.label}
                                    subtitle={label(selected.type)}
                                    onBack={() => setSelection({ group: selection.group, key: selection.key })}
                                    onClose={() => setSelection(null)}
                                    isAr={isAr}
                                    actions={[
                                        { icon: <ChevronDown className="h-4 w-4 rotate-180" />, label: L('Move up', 'لأعلى'), onClick: () => blockOp(selection, selectedBlock.id, 'up') },
                                        { icon: <ChevronDown className="h-4 w-4" />, label: L('Move down', 'لأسفل'), onClick: () => blockOp(selection, selectedBlock.id, 'down') },
                                        { icon: <Copy className="h-4 w-4" />, label: L('Duplicate', 'تكرار'), onClick: () => blockOp(selection, selectedBlock.id, 'duplicate') },
                                        { icon: <Trash2 className="h-4 w-4" />, label: L('Remove block', 'حذف الكتلة'), onClick: () => blockOp(selection, selectedBlock.id, 'remove'), danger: true },
                                    ]}
                                >
                                    <Fields fields={selectedBlockDef.fields} settings={selectedBlock.settings} lang={lang} isAr={isAr} catalog={catalog} idPrefix={`b-${selectedBlock.id}`} onChange={(k, v) => setBlock(selection, selectedBlock.id, k, v)} />
                                </SettingsPanel>
                            ) : (
                                <SettingsPanel
                                    title={label(selected.type)}
                                    subtitle={selectedDef.description}
                                    onClose={() => setSelection(null)}
                                    isAr={isAr}
                                    actions={[
                                        { icon: selected.is_visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />, label: selected.is_visible ? L('Hide section', 'إخفاء القسم') : L('Show section', 'إظهار القسم'), onClick: () => toggleSection(selection) },
                                        { icon: <Copy className="h-4 w-4" />, label: L('Duplicate', 'تكرار'), onClick: () => duplicateSection(selection) },
                                        { icon: <Trash2 className="h-4 w-4" />, label: L('Remove section', 'حذف القسم'), onClick: () => window.confirm(L('Remove this section?', 'حذف هذا القسم؟')) && removeSection(selection), danger: true },
                                    ]}
                                    tabs={
                                        <div className="flex gap-1 border-b border-line px-3" role="tablist">
                                            {([['content', L('Content', 'المحتوى'), Layers], ['style', L('Style', 'التنسيق'), Paintbrush], ['css', 'CSS', Code2]] as const).map(([k, t, Icon]) => (
                                                <button key={k} role="tab" aria-selected={tab === k} type="button" onClick={() => setTab(k)} className={cn('-mb-px inline-flex items-center gap-1.5 border-b-2 px-2.5 py-2.5 text-xs font-semibold', tab === k ? 'border-primary text-ink' : 'border-transparent text-ink-muted hover:text-ink')}>
                                                    <Icon className="h-3.5 w-3.5" />{t}
                                                </button>
                                            ))}
                                        </div>
                                    }
                                >
                                    {tab === 'content' ? (
                                        <>
                                            <Fields fields={selectedDef.fields} settings={selected.settings} lang={lang} isAr={isAr} catalog={catalog} idPrefix={`s-${selected.key}`} onChange={(k, v) => setSetting(selection, k, v)} />
                                            {selectedDef.max_blocks > 0 ? (
                                                <div className="flex flex-col gap-1 border-t border-line pt-4">
                                                    <p className="text-xs font-semibold">{L('Blocks', 'الكتل')} <span className="font-normal text-ink-muted">({blocksOf(selected.settings).length}/{selectedDef.max_blocks})</span></p>
                                                    {blocksOf(selected.settings).map((b) => {
                                                        const bt = blockTitle(selected, b);
                                                        return (
                                                            <button key={b.id} type="button" onClick={() => setSelection({ ...selection, blockId: b.id })} className={cn('flex items-center justify-between gap-2 rounded-md border border-line px-3 py-2 text-start text-xs hover:bg-secondary', b.hidden && 'opacity-50')}>
                                                                <span className="min-w-0 truncate"><span className="text-ink-muted">{bt.kind}</span>{bt.text ? ` · ${bt.text}` : ''}</span>
                                                                <ChevronRight className="h-3.5 w-3.5 shrink-0 text-ink-muted rtl:rotate-180" />
                                                            </button>
                                                        );
                                                    })}
                                                    {blocksOf(selected.settings).length < selectedDef.max_blocks ? (
                                                        <button type="button" onClick={() => openBlockPicker(selection)} className="mt-1 inline-flex items-center gap-1.5 text-xs font-semibold text-ink-link"><Plus className="h-3.5 w-3.5" />{L('Add block', 'إضافة كتلة')}</button>
                                                    ) : null}
                                                </div>
                                            ) : null}
                                        </>
                                    ) : tab === 'style' ? (
                                        <Fields
                                            fields={styleFields}
                                            settings={{ ...((selectedDef.defaults.style as Settings) ?? {}), ...((selected.settings.style as Settings) ?? {}) }}
                                            lang={lang} isAr={isAr} catalog={catalog} idPrefix={`st-${selected.key}`}
                                            onChange={(k, v) => setStyle(selection, k, v)}
                                        />
                                    ) : (
                                        <CssEditor
                                            value={String(selected.settings.custom_css ?? '')}
                                            onChange={(v) => setSetting(selection, 'custom_css', v)}
                                            isAr={isAr}
                                            hint={
                                                <>
                                                    {L('Use & for this section. Classes you can target: ', 'استخدم & للإشارة لهذا القسم. أصناف يمكنك استهدافها: ')}
                                                    <code className="font-mono">.mv-h2 .mv-btn .mv-card .mv-media .mv-container .mv-hero__title</code>
                                                </>
                                            }
                                            example={`& {\n  background: #0b0b0b;\n  color: #f5f5f5;\n}\n& .mv-h2 {\n  font-size: 64px;\n  letter-spacing: -.03em;\n  text-transform: uppercase;\n}\n& .mv-card:hover {\n  transform: translateY(-4px);\n}`}
                                        />
                                    )}
                                </SettingsPanel>
                            )
                        ) : panel === 'theme' ? (
                            <ThemePanel theme={initialTheme} isAr={isAr} onClose={() => setPanel(null)} onSaved={() => setFrameKey((k) => k + 1)} onCss={(css) => postToFrame({ type: 'theme-css', html: css })} flash={flash} />
                        ) : panel === 'versions' ? (
                            <SettingsPanel title={L('Version history', 'سجل الإصدارات')} subtitle={L('Every publish is saved. Restore one as your draft.', 'يُحفظ كل نشر. استعد أي إصدار كمسودة.')} onClose={() => setPanel(null)} isAr={isAr}>
                                {versions.length === 0 ? <p className="text-sm text-ink-muted">{L('Not published yet.', 'لم يُنشر بعد.')}</p> : null}
                                <ul className="flex flex-col gap-2">
                                    {versions.map((v, i) => (
                                        <li key={v.id} className="flex items-center justify-between gap-3 rounded-md border border-line px-3 py-2.5 text-sm">
                                            <div>
                                                <p className="font-semibold">v{v.version_number}{i === 0 ? <span className="ms-2 rounded-full bg-success-subtle px-2 py-0.5 text-[0.6875rem] text-success">{L('Live', 'منشور')}</span> : null}</p>
                                                <p className="text-xs text-ink-muted">{v.created_at ? new Date(v.created_at).toLocaleString(isAr ? 'ar-EG' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' }) : ''} · {v.sections} {L('sections', 'أقسام')}</p>
                                            </div>
                                            <button type="button" onClick={() => restore(v)} className="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold hover:bg-secondary"><RotateCcw className="h-3.5 w-3.5" />{L('Restore', 'استعادة')}</button>
                                        </li>
                                    ))}
                                </ul>
                            </SettingsPanel>
                        ) : panel === 'page' && page ? (
                            <PagePanel key={page.id} page={page} isAr={isAr} templates={templates} onClose={() => setPanel(null)} flash={flash}
                                onSaved={(p) => setPages((ps) => ps.map((x) => (x.id === p.id ? { ...x, ...p, is_home: p.is_home } : p.is_home ? { ...x, is_home: false } : x)))}
                                onDeleted={() => { setPages((ps) => ps.filter((x) => x.id !== page.id)); setPageId(pages.find((x) => x.is_home)?.id ?? null); setPanel(null); }}
                                onTemplate={() => { void load('template', page.id); setFrameKey((k) => k + 1); }} />
                        ) : (
                            <div className="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center">
                                <span className="grid h-12 w-12 place-items-center rounded-full bg-accent-subtle text-ink-accent"><Sparkles className="h-5 w-5" /></span>
                                <p className="font-display text-base">{L('Select a section to edit', 'اختر قسماً لتعديله')}</p>
                                <p className="text-sm text-ink-muted">{L('Click it in the preview or in the list. Use the brush for theme colours, fonts and store-wide CSS.', 'اضغط عليه في المعاينة أو القائمة. استخدم الفرشاة للألوان والخطوط و CSS للمتجر كله.')}</p>
                            </div>
                        )}
                    </aside>
                </div>
            </div>

            {picker ? <SectionPicker group={picker} sectionTypes={sectionTypes} isAr={isAr} onClose={() => setPicker(null)} onPick={(type) => addSection(picker, type)} /> : null}
            {blockPicker ? (() => {
                const s = findSection(blockPicker);
                const defs = s ? blockDefs(sectionTypes[s.type]) : {};
                const keys = Object.keys(defs);
                return (
                    <Modal title={L('Add block', 'إضافة كتلة')} onClose={() => setBlockPicker(null)} isAr={isAr}>
                        <div className="grid grid-cols-2 gap-2">
                            {keys.map((k) => (
                                <button key={k} type="button" onClick={() => addBlock(blockPicker, k)} className="rounded-lg border border-line px-3 py-3 text-start text-sm font-medium hover:border-line-focus hover:bg-secondary">
                                    {isAr ? defs[k].label_ar : defs[k].label}
                                </button>
                            ))}
                        </div>
                    </Modal>
                );
            })() : null}
            {newPage ? <NewPageModal pages={pages} isAr={isAr} onClose={() => setNewPage(false)} onCreated={(p) => { setPages((ps) => [...ps, p]); setPageId(p.id); setNewPage(false); }} flash={flash} /> : null}
        </>
    );
}

/* ── Small building blocks ───────────────────────────── */

function IconBtn({ label, onClick, children, disabled, size = 'sm', active, className }: { label: string; onClick: () => void; children: ReactNode; disabled?: boolean; size?: 'sm' | 'md'; active?: boolean; className?: string }) {
    return (
        <button type="button" title={label} aria-label={label} onClick={onClick} disabled={disabled}
            className={cn('grid shrink-0 place-items-center rounded-full text-ink-muted hover:bg-secondary hover:text-ink disabled:opacity-30', size === 'md' ? 'h-9 w-9' : 'h-6 w-6', active && 'bg-secondary text-ink', className)}>
            {children}
        </button>
    );
}

function Seg({ active, onClick, label, children }: { active: boolean; onClick: () => void; label: string; children: ReactNode }) {
    return (
        <button type="button" aria-pressed={active} title={label} aria-label={label} onClick={onClick} className={cn('grid h-7 min-w-7 place-items-center rounded-full px-1.5', active ? 'bg-surface text-ink shadow-sm' : 'text-ink-muted hover:text-ink')}>
            {children}
        </button>
    );
}

function Fields({ fields, settings, lang, isAr, catalog, idPrefix, onChange }: { fields: FieldDef[]; settings: Settings; lang: 'en' | 'ar'; isAr: boolean; catalog: CatalogRefs; idPrefix: string; onChange: (k: string, v: unknown) => void }) {
    return (
        <div className="flex flex-col gap-4">
            {fields.filter((f) => isFieldVisible(f, settings)).map((f) => (
                <FieldInput key={f.id} field={f} settings={settings} lang={lang} isAr={isAr} catalog={catalog} idPrefix={idPrefix} onChange={onChange} />
            ))}
        </div>
    );
}

function SettingsPanel({ title, subtitle, onBack, onClose, actions = [], tabs, children, isAr }: { title: string; subtitle?: string; onBack?: () => void; onClose: () => void; actions?: { icon: ReactNode; label: string; onClick: () => void; danger?: boolean }[]; tabs?: ReactNode; children: ReactNode; isAr: boolean }) {
    const [menu, setMenu] = useState(false);
    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex items-start gap-2 border-b border-line px-4 py-3">
                {onBack ? <IconBtn label={isAr ? 'رجوع' : 'Back'} onClick={onBack} size="md"><ArrowLeft className="h-4 w-4 rtl:rotate-180" /></IconBtn> : null}
                <div className="min-w-0 flex-1 pt-1">
                    <h2 className="truncate font-display text-[0.9375rem]">{title}</h2>
                    {subtitle ? <p className="mt-0.5 line-clamp-2 text-xs text-ink-muted">{subtitle}</p> : null}
                </div>
                {actions.length ? (
                    <div className="relative">
                        <IconBtn label={isAr ? 'المزيد' : 'More'} onClick={() => setMenu((m) => !m)} size="md" active={menu}><MoreHorizontal className="h-4 w-4" /></IconBtn>
                        {menu ? (
                            <div className="absolute end-0 top-10 z-20 w-48 overflow-hidden rounded-lg border border-line bg-elevated py-1 shadow-lg" onMouseLeave={() => setMenu(false)}>
                                {actions.map((a) => (
                                    <button key={a.label} type="button" onClick={() => { setMenu(false); a.onClick(); }} className={cn('flex w-full items-center gap-2 px-3 py-2 text-start text-sm hover:bg-secondary', a.danger && 'text-danger')}>
                                        {a.icon}{a.label}
                                    </button>
                                ))}
                            </div>
                        ) : null}
                    </div>
                ) : null}
                <IconBtn label={isAr ? 'إغلاق' : 'Close'} onClick={onClose} size="md"><X className="h-4 w-4" /></IconBtn>
            </div>
            {tabs}
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4">{children}</div>
        </div>
    );
}

function CssEditor({ value, onChange, hint, example, isAr }: { value: string; onChange: (v: string) => void; hint: ReactNode; example: string; isAr: boolean }) {
    return (
        <div className="flex flex-col gap-3">
            <p className="text-xs leading-relaxed text-ink-muted">{hint}</p>
            <textarea
                dir="ltr" spellCheck={false} rows={18} value={value} placeholder={example}
                onChange={(e) => onChange(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Tab') {
                        e.preventDefault();
                        const t = e.currentTarget;
                        const { selectionStart: a, selectionEnd: b } = t;
                        const next = `${t.value.slice(0, a)}  ${t.value.slice(b)}`;
                        onChange(next);
                        requestAnimationFrame(() => { t.selectionStart = t.selectionEnd = a + 2; });
                    }
                }}
                className={cn(inputCls, 'min-h-[18rem] resize-y bg-[#0f1115] font-mono text-xs leading-relaxed text-[#e6e6e6] placeholder:text-[#6b7280]')}
            />
            {!value ? <button type="button" onClick={() => onChange(example)} className="self-start text-xs font-semibold text-ink-link">{isAr ? 'أدرج مثالاً' : 'Insert example'}</button> : null}
        </div>
    );
}

function Modal({ title, onClose, children, isAr }: { title: string; onClose: () => void; children: ReactNode; isAr: boolean }) {
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onClose]);
    return (
        <div className="fixed inset-0 z-[70] grid place-items-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-label={title} onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
            <div className="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-line bg-elevated shadow-lg" dir={isAr ? 'rtl' : 'ltr'}>
                <div className="flex items-center justify-between border-b border-line px-5 py-3">
                    <h2 className="font-display text-base">{title}</h2>
                    <IconBtn label={isAr ? 'إغلاق' : 'Close'} onClick={onClose} size="md"><X className="h-4 w-4" /></IconBtn>
                </div>
                <div className="overflow-y-auto p-5">{children}</div>
            </div>
        </div>
    );
}

function SectionPicker({ group, sectionTypes, isAr, onClose, onPick }: { group: GroupKey; sectionTypes: Record<string, SectionDef>; isAr: boolean; onClose: () => void; onPick: (type: string) => void }) {
    const [q, setQ] = useState('');
    const entries = Object.entries(sectionTypes).filter(([, d]) => d.group === group);
    const filtered = entries.filter(([k, d]) => `${k} ${d.label} ${d.label_ar} ${d.description}`.toLowerCase().includes(q.toLowerCase()));
    const byCat = filtered.reduce<Record<string, [string, SectionDef][]>>((acc, e) => {
        (acc[e[1].category] ??= []).push(e);
        return acc;
    }, {});
    return (
        <Modal title={isAr ? 'إضافة قسم' : 'Add section'} onClose={onClose} isAr={isAr}>
            <div className="relative mb-4">
                <Search className="pointer-events-none absolute start-3 top-2.5 h-4 w-4 text-ink-muted" />
                <input autoFocus className={cn(inputCls, 'ps-9')} placeholder={isAr ? 'ابحث عن قسم…' : 'Search sections…'} value={q} onChange={(e) => setQ(e.target.value)} />
            </div>
            <div className="flex flex-col gap-5">
                {Object.entries(byCat).map(([cat, list]) => (
                    <div key={cat}>
                        <p className="mb-2 text-label uppercase text-ink-muted">{(categoryLabels[cat] ?? [cat, cat])[isAr ? 1 : 0]}</p>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {list.map(([type, d]) => (
                                <button key={type} type="button" onClick={() => onPick(type)} className="flex flex-col gap-0.5 rounded-lg border border-line px-3 py-2.5 text-start hover:border-line-focus hover:bg-secondary">
                                    <span className="text-sm font-semibold">{isAr ? d.label_ar : d.label}</span>
                                    <span className="text-xs text-ink-muted">{d.description}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                ))}
                {filtered.length === 0 ? <p className="text-sm text-ink-muted">{isAr ? 'لا نتائج' : 'No matches'}</p> : null}
            </div>
        </Modal>
    );
}

function PageSwitcher({ pages, page, isAr, onPick, onNew }: { pages: PageSummary[]; page: PageSummary | null; isAr: boolean; onPick: (id: number) => void; onNew: () => void }) {
    const [open, setOpen] = useState(false);
    return (
        <div className="relative">
            <button type="button" onClick={() => setOpen((o) => !o)} aria-expanded={open} className="inline-flex h-9 max-w-[14rem] items-center gap-2 rounded-full border border-line px-3 text-sm font-semibold hover:bg-secondary">
                {page?.is_home ? <Home className="h-4 w-4 shrink-0" /> : <LayoutTemplate className="h-4 w-4 shrink-0" />}
                <span className="truncate">{page ? (isAr && page.title_ar ? page.title_ar : page.title) : isAr ? 'اختر صفحة' : 'Choose page'}</span>
                <ChevronDown className="h-4 w-4 shrink-0" />
            </button>
            {open ? (
                <div className="absolute start-0 top-11 z-30 w-64 overflow-hidden rounded-lg border border-line bg-elevated py-1 shadow-lg" onMouseLeave={() => setOpen(false)}>
                    {pages.map((p) => (
                        <button key={p.id} type="button" onClick={() => { onPick(p.id); setOpen(false); }} className={cn('flex w-full items-center gap-2 px-3 py-2 text-start text-sm hover:bg-secondary', p.id === page?.id && 'bg-accent-subtle')}>
                            {p.is_home ? <Home className="h-4 w-4 text-ink-muted" /> : <LayoutTemplate className="h-4 w-4 text-ink-muted" />}
                            <span className="min-w-0 flex-1 truncate">{isAr && p.title_ar ? p.title_ar : p.title}</span>
                            {p.has_changes ? <span className="h-1.5 w-1.5 rounded-full bg-warning" /> : null}
                        </button>
                    ))}
                    <button type="button" onClick={() => { onNew(); setOpen(false); }} className="flex w-full items-center gap-2 border-t border-line px-3 py-2 text-start text-sm font-semibold text-ink-link hover:bg-secondary">
                        <FilePlus2 className="h-4 w-4" />{isAr ? 'صفحة جديدة' : 'New page'}
                    </button>
                </div>
            ) : null}
        </div>
    );
}

function NewPageModal({ pages, isAr, onClose, onCreated, flash }: { pages: PageSummary[]; isAr: boolean; onClose: () => void; onCreated: (p: PageSummary) => void; flash: (t: string, tone?: 'ok' | 'err') => void }) {
    const [title, setTitle] = useState('');
    const [titleAr, setTitleAr] = useState('');
    const [slug, setSlug] = useState('');
    const [dup, setDup] = useState('');
    const [busy, setBusy] = useState(false);
    const submit = async () => {
        setBusy(true);
        try {
            const { data } = await axios.post<{ page: PageSummary }>('/builder/pages', { title, title_ar: titleAr || null, slug: slug || null, duplicate_of: dup ? Number(dup) : null });
            onCreated(data.page);
        } catch (e) {
            flash(errorText(e), 'err');
        } finally {
            setBusy(false);
        }
    };
    return (
        <Modal title={isAr ? 'صفحة جديدة' : 'New page'} onClose={onClose} isAr={isAr}>
            <form className="flex flex-col gap-4" onSubmit={(e) => { e.preventDefault(); void submit(); }}>
                <FieldShell label={isAr ? 'العنوان (إنجليزي)' : 'Title (English)'} htmlFor="np-title"><input id="np-title" required autoFocus className={inputCls} value={title} onChange={(e) => setTitle(e.target.value)} /></FieldShell>
                <FieldShell label={isAr ? 'العنوان (عربي)' : 'Title (Arabic)'} htmlFor="np-title-ar"><input id="np-title-ar" dir="rtl" className={inputCls} value={titleAr} onChange={(e) => setTitleAr(e.target.value)} /></FieldShell>
                <FieldShell label={isAr ? 'الرابط' : 'URL'} htmlFor="np-slug" hint={`/pages/${slug || title.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-') || '…'}`}><input id="np-slug" dir="ltr" className={inputCls} value={slug} onChange={(e) => setSlug(e.target.value)} placeholder="lookbook" /></FieldShell>
                <FieldShell label={isAr ? 'ابدأ من' : 'Start from'} htmlFor="np-dup">
                    <select id="np-dup" className={inputCls} value={dup} onChange={(e) => setDup(e.target.value)}>
                        <option value="">{isAr ? 'صفحة فارغة' : 'Blank page'}</option>
                        {pages.map((p) => <option key={p.id} value={p.id}>{isAr ? 'نسخة من ' : 'Copy of '}{p.title}</option>)}
                    </select>
                </FieldShell>
                <button type="submit" disabled={busy || !title.trim()} className="inline-flex h-10 items-center justify-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-primary-fg disabled:opacity-60">
                    {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}{isAr ? 'إنشاء' : 'Create page'}
                </button>
            </form>
        </Modal>
    );
}

function PagePanel({ page, isAr, templates, onClose, onSaved, onDeleted, onTemplate, flash }: { page: PageSummary; isAr: boolean; templates: string[]; onClose: () => void; onSaved: (p: PageSummary) => void; onDeleted: () => void; onTemplate: () => void; flash: (t: string, tone?: 'ok' | 'err') => void }) {
    const [title, setTitle] = useState(page.title);
    const [titleAr, setTitleAr] = useState(page.title_ar ?? '');
    const [slug, setSlug] = useState(page.slug);
    const [busy, setBusy] = useState(false);
    const templateNames: Record<string, [string, string]> = { fashion: ['Fashion', 'أزياء'], food: ['Food & Bakery', 'أطعمة ومخبوزات'], handmade: ['Handmade', 'مشغولات يدوية'], electronics: ['Electronics', 'إلكترونيات'], streetwear: ['Streetwear drops', 'ستريت وير'] };
    const save = async (extra: Record<string, unknown> = {}) => {
        setBusy(true);
        try {
            const { data } = await axios.patch<{ page: PageSummary }>(`/builder/pages/${page.id}`, { title, title_ar: titleAr || null, slug, ...extra });
            onSaved(data.page);
            flash(isAr ? 'تم الحفظ' : 'Saved');
        } catch (e) {
            flash(errorText(e), 'err');
        } finally {
            setBusy(false);
        }
    };
    const applyTemplate = async (t: string) => {
        if (!window.confirm(isAr ? 'سيستبدل القالب أقسام الصفحة الرئيسية والمظهر. متابعة؟' : 'This replaces the home page sections and theme. Continue?')) return;
        try {
            await axios.post('/builder/template', { business_type: t }, { headers: { Accept: 'application/json' } });
            onTemplate();
            flash(isAr ? 'تم تطبيق القالب كمسودة' : 'Template applied as a draft');
        } catch (e) {
            flash(errorText(e), 'err');
        }
    };
    const remove = async () => {
        if (!window.confirm(isAr ? 'حذف هذه الصفحة نهائياً؟' : 'Delete this page permanently?')) return;
        try {
            await axios.delete(`/builder/pages/${page.id}`);
            onDeleted();
        } catch (e) {
            flash(errorText(e), 'err');
        }
    };
    return (
        <SettingsPanel title={isAr ? 'إعدادات الصفحة' : 'Page settings'} subtitle={page.is_home ? (isAr ? 'الصفحة الرئيسية' : 'Home page') : `/pages/${page.slug}`} onClose={onClose} isAr={isAr}>
            <div className="flex flex-col gap-4">
                <FieldShell label={isAr ? 'العنوان (إنجليزي)' : 'Title (English)'} htmlFor="pg-title"><input id="pg-title" className={inputCls} value={title} onChange={(e) => setTitle(e.target.value)} /></FieldShell>
                <FieldShell label={isAr ? 'العنوان (عربي)' : 'Title (Arabic)'} htmlFor="pg-title-ar"><input id="pg-title-ar" dir="rtl" className={inputCls} value={titleAr} onChange={(e) => setTitleAr(e.target.value)} /></FieldShell>
                {!page.is_home ? <FieldShell label={isAr ? 'الرابط' : 'URL slug'} htmlFor="pg-slug"><input id="pg-slug" dir="ltr" className={inputCls} value={slug} onChange={(e) => setSlug(e.target.value)} /></FieldShell> : null}
                <div className="flex flex-wrap gap-2">
                    <button type="button" disabled={busy} onClick={() => save()} className="inline-flex h-9 items-center rounded-full bg-primary px-4 text-sm font-semibold text-primary-fg disabled:opacity-60">{isAr ? 'حفظ' : 'Save'}</button>
                    {!page.is_home ? <button type="button" disabled={busy} onClick={() => save({ is_home: true })} className="inline-flex h-9 items-center gap-1.5 rounded-full border border-line px-4 text-sm font-semibold hover:bg-secondary"><Home className="h-4 w-4" />{isAr ? 'اجعلها الرئيسية' : 'Make home page'}</button> : null}
                </div>
                {page.is_home ? (
                    <div className="flex flex-col gap-2 border-t border-line pt-4">
                        <p className="text-xs font-semibold">{isAr ? 'ابدأ من قالب' : 'Start from a template'}</p>
                        <div className="grid grid-cols-2 gap-2">
                            {templates.map((t) => <button key={t} type="button" onClick={() => applyTemplate(t)} className="rounded-md border border-line px-3 py-2 text-start text-xs font-semibold hover:bg-secondary">{(templateNames[t] ?? [t, t])[isAr ? 1 : 0]}</button>)}
                        </div>
                    </div>
                ) : (
                    <button type="button" onClick={remove} className="mt-4 inline-flex items-center gap-1.5 self-start text-sm font-semibold text-danger"><Trash2 className="h-4 w-4" />{isAr ? 'حذف الصفحة' : 'Delete page'}</button>
                )}
            </div>
        </SettingsPanel>
    );
}

function ThemePanel({ theme, isAr, onClose, onSaved, onCss, flash }: { theme: ThemeProps; isAr: boolean; onClose: () => void; onSaved: () => void; onCss: (css: string) => void; flash: (t: string, tone?: 'ok' | 'err') => void }) {
    const [t, setT] = useState(theme);
    const [busy, setBusy] = useState(false);
    const fonts = ['Cairo', 'Tajawal', 'IBM Plex Sans Arabic', 'Noto Kufi Arabic', 'Almarai', 'Amiri', 'Inter', 'Outfit', 'Manrope', 'Space Grotesk', 'Archivo', 'Bebas Neue', 'Anton', 'Playfair Display', 'Cormorant Garamond', 'DM Serif Display', 'Syne', 'Unbounded', 'JetBrains Mono'];
    const setS = (k: string, v: unknown) => setT((x) => ({ ...x, settings: { ...x.settings, [k]: v } }));
    const save = async () => {
        setBusy(true);
        try {
            await axios.put('/builder/theme', { primary_color: t.primary_color, accent_color: t.accent_color, font_heading: t.font_heading, font_body: t.font_body, corner_radius: t.corner_radius, color_mode: t.color_mode, custom_css: t.custom_css ?? '', settings: t.settings });
            Object.assign(theme, t);
            onSaved();
            flash(isAr ? 'تم حفظ المظهر' : 'Theme saved');
        } catch (e) {
            flash(errorText(e), 'err');
        } finally {
            setBusy(false);
        }
    };
    const color = (k: 'primary_color' | 'accent_color', l: string) => (
        <FieldShell label={l} htmlFor={`th-${k}`}>
            <div className="flex gap-2"><input type="color" aria-label={l} className="h-9 w-11 rounded-md border border-line-control p-1" value={t[k]} onChange={(e) => setT({ ...t, [k]: e.target.value })} /><input id={`th-${k}`} dir="ltr" className={cn(inputCls, 'font-mono')} value={t[k]} onChange={(e) => setT({ ...t, [k]: e.target.value })} /></div>
        </FieldShell>
    );
    const sColor = (k: string, l: string) => (
        <FieldShell label={l} htmlFor={`th-${k}`}>
            <div className="flex gap-2"><input type="color" aria-label={l} className="h-9 w-11 rounded-md border border-line-control p-1" value={/^#[0-9a-f]{6}$/i.test(String(t.settings[k] ?? '')) ? String(t.settings[k]) : '#ffffff'} onChange={(e) => setS(k, e.target.value)} /><input id={`th-${k}`} dir="ltr" className={cn(inputCls, 'font-mono')} placeholder={isAr ? 'تلقائي' : 'Auto'} value={String(t.settings[k] ?? '')} onChange={(e) => setS(k, e.target.value)} /></div>
        </FieldShell>
    );
    return (
        <SettingsPanel title={isAr ? 'المظهر' : 'Theme'} subtitle={isAr ? 'ألوان وخطوط وأزرار و CSS للمتجر كله.' : 'Colours, type, buttons and store-wide CSS.'} onClose={onClose} isAr={isAr}>
            <div className="flex flex-col gap-4">
                <div className="grid grid-cols-2 gap-3">{color('primary_color', isAr ? 'الأساسي' : 'Primary')}{color('accent_color', isAr ? 'المميز' : 'Accent')}</div>
                <div className="grid grid-cols-2 gap-3">{sColor('background_color', isAr ? 'الخلفية' : 'Background')}{sColor('text_color', isAr ? 'النص' : 'Text')}</div>
                <FieldShell label={isAr ? 'الوضع' : 'Mode'} htmlFor="th-mode"><select id="th-mode" className={inputCls} value={t.color_mode} onChange={(e) => setT({ ...t, color_mode: e.target.value })}><option value="light">{isAr ? 'فاتح' : 'Light'}</option><option value="dark">{isAr ? 'داكن' : 'Dark'}</option></select></FieldShell>
                <div className="grid grid-cols-2 gap-3">
                    <FieldShell label={isAr ? 'خط العناوين' : 'Heading font'} htmlFor="th-fh"><input id="th-fh" list="th-fonts" className={inputCls} value={t.font_heading} onChange={(e) => setT({ ...t, font_heading: e.target.value })} /></FieldShell>
                    <FieldShell label={isAr ? 'خط النص' : 'Body font'} htmlFor="th-fb"><input id="th-fb" list="th-fonts" className={inputCls} value={t.font_body} onChange={(e) => setT({ ...t, font_body: e.target.value })} /></FieldShell>
                    <datalist id="th-fonts">{fonts.map((f) => <option key={f} value={f} />)}</datalist>
                </div>
                <p className="-mt-2 text-[0.6875rem] text-ink-muted">{isAr ? 'أي خط من Google Fonts بالاسم.' : 'Any Google Fonts family by name.'}</p>
                <div className="grid grid-cols-2 gap-3">
                    <FieldShell label={isAr ? 'وزن العناوين' : 'Heading weight'} htmlFor="th-hw"><select id="th-hw" className={inputCls} value={String(t.settings.heading_weight ?? 600)} onChange={(e) => setS('heading_weight', Number(e.target.value))}>{[300, 400, 500, 600, 700, 800].map((w) => <option key={w} value={w}>{w}</option>)}</select></FieldShell>
                    <FieldShell label={isAr ? 'الحواف' : 'Corners'} htmlFor="th-r"><select id="th-r" className={inputCls} value={t.corner_radius} onChange={(e) => setT({ ...t, corner_radius: e.target.value })}>{['none', 'sm', 'md', 'lg', 'full'].map((r) => <option key={r} value={r}>{r}</option>)}</select></FieldShell>
                </div>
                <FieldShell label={`${isAr ? 'تباعد أحرف العناوين' : 'Heading letter spacing'} (${String(t.settings.heading_letter_spacing ?? 0)}%)`} htmlFor="th-ls"><input id="th-ls" type="range" min={-8} max={30} value={Number(t.settings.heading_letter_spacing ?? 0)} onChange={(e) => setS('heading_letter_spacing', Number(e.target.value))} /></FieldShell>
                <FieldShell label={`${isAr ? 'عرض الصفحة' : 'Page width'} (${String(t.settings.page_width ?? 1280)}px)`} htmlFor="th-pw"><input id="th-pw" type="range" min={960} max={2000} step={20} value={Number(t.settings.page_width ?? 1280)} onChange={(e) => setS('page_width', Number(e.target.value))} /></FieldShell>
                {([['heading_uppercase', isAr ? 'عناوين بأحرف كبيرة' : 'Uppercase headings'], ['button_uppercase', isAr ? 'أزرار بأحرف كبيرة' : 'Uppercase buttons']] as const).map(([k, l]) => (
                    <label key={k} className="flex items-center justify-between text-xs font-semibold">{l}<input type="checkbox" checked={Boolean(t.settings[k])} onChange={(e) => setS(k, e.target.checked)} /></label>
                ))}
                <FieldShell label={isAr ? 'نمط الأزرار' : 'Button style'} htmlFor="th-bs"><select id="th-bs" className={inputCls} value={String(t.settings.button_style ?? 'solid')} onChange={(e) => setS('button_style', e.target.value)}><option value="solid">{isAr ? 'ممتلئ' : 'Solid'}</option><option value="outline">{isAr ? 'إطار' : 'Outline'}</option></select></FieldShell>
                <div className="border-t border-line pt-4">
                    <p className="mb-2 text-xs font-semibold">{isAr ? 'CSS للمتجر كله' : 'Store-wide custom CSS'}</p>
                    <CssEditor
                        value={t.custom_css ?? ''} isAr={isAr}
                        onChange={(v) => { setT({ ...t, custom_css: v }); onCss(v); }}
                        hint={<>{isAr ? 'يُطبَّق على كل الصفحات. متغيرات مفيدة: ' : 'Applies to every page. Handy variables: '}<code className="font-mono">--primary --accent --bg --fg --radius --font-heading</code></>}
                        example={`.mv-body {\n  letter-spacing: .01em;\n}\n.mv-h2 {\n  font-style: italic;\n}\n.mv-btn {\n  border-radius: 0;\n}`}
                    />
                </div>
                <button type="button" disabled={busy} onClick={save} className="inline-flex h-10 items-center justify-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-primary-fg disabled:opacity-60">
                    {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : null}{isAr ? 'حفظ المظهر' : 'Save theme'}
                </button>
                <p className="text-[0.6875rem] text-ink-muted">{isAr ? 'تُطبَّق تغييرات المظهر على المتجر فور الحفظ.' : 'Theme changes apply to the live store as soon as you save.'}</p>
            </div>
        </SettingsPanel>
    );
}

