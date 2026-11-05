// Shared Inertia props (HandleInertiaRequests::share). Page props are declared per page.

export type Locale = 'ar' | 'en';
export type Role = 'owner' | 'manager' | 'accountant' | 'staff';
export type StoreStatus = 'opening_soon' | 'live' | 'suspended';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    email_verified: boolean;
    is_platform_admin: boolean;
}

export interface CurrentStore {
    id: number;
    name: string;
    subdomain: string;
    url: string;
    status: StoreStatus;
    role: Role | null;
    permissions: string[];
}

export interface StoreSummary {
    id: number;
    name: string;
    subdomain: string;
}

export interface SharedProps {
    [key: string]: unknown;
    locale: Locale;
    dir: 'rtl' | 'ltr';
    translations: Record<string, string>;
    domain: string;
    auth: { user: AuthUser | null };
    store: CurrentStore | null;
    stores: StoreSummary[];
    flash: { success: string | null; error: string | null };
    errors: Record<string, string>;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
