// Shapes shared by the store editor. They mirror App\Modules\Builder\Services\SectionRegistry.

export type FieldType =
    | 'text' | 'textarea' | 'richtext' | 'links' | 'code' | 'url' | 'image' | 'video' | 'color'
    | 'select' | 'range' | 'number' | 'checkbox' | 'datetime' | 'product' | 'product_list' | 'category' | 'category_list';

export interface FieldDef {
    id: string;
    type: FieldType;
    label: string;
    label_ar: string;
    tr?: boolean;
    default?: unknown;
    options?: Record<string, string>;
    min?: number;
    max?: number;
    step?: number;
    unit?: string;
    language?: string;
    show_if?: Record<string, string>;
}

export interface BlockDef {
    label: string;
    label_ar: string;
    fields: FieldDef[];
}

export interface SectionDef {
    label: string;
    label_ar: string;
    category: string;
    group: 'template' | 'header' | 'footer';
    description: string;
    fields: FieldDef[];
    blocks: Record<string, BlockDef> | [];
    max_blocks: number;
    defaults: Settings;
}

export type Settings = Record<string, unknown>;

export interface Block {
    id: string;
    type: string;
    hidden?: boolean;
    settings: Settings;
}

export interface EditorSection {
    key: string;
    id: number | null;
    type: string;
    is_visible: boolean;
    settings: Settings;
}

export interface PageSummary {
    id: number;
    slug: string;
    title: string;
    title_ar: string | null;
    is_home: boolean;
    status: string;
    published_at: string | null;
    updated_at: string | null;
    has_changes: boolean;
    url: string;
}

export interface Version {
    id: number;
    version_number: number;
    created_at: string | null;
    sections: number;
}

export interface CatalogRefs {
    products: { id: number; name: string; image: string | null }[];
    categories: { id: number; name: string; name_ar: string | null }[];
}

export interface MediaFileRef {
    id: number;
    url: string;
    mime_type: string;
    alt_en: string | null;
}

export const uid = () => Math.random().toString(36).slice(2, 10);

export function blocksOf(settings: Settings): Block[] {
    return Array.isArray(settings.blocks) ? (settings.blocks as Block[]) : [];
}

export function blockDefs(def: SectionDef | undefined): Record<string, BlockDef> {
    return def && !Array.isArray(def.blocks) ? def.blocks : {};
}

/** Defaults for a block's fields (with Arabic twins for translatable ones). */
export function blockDefaults(def: BlockDef): Settings {
    const out: Settings = {};
    def.fields.forEach((f) => {
        out[f.id] = f.default ?? (f.type === 'checkbox' ? false : '');
        if (f.tr) out[`${f.id}_ar`] = (f as FieldDef & { default_ar?: string }).default_ar ?? '';
    });
    return out;
}

export function deepClone<T>(value: T): T {
    return JSON.parse(JSON.stringify(value)) as T;
}
