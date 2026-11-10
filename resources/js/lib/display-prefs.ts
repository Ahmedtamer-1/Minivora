// Display and accessibility preferences (Settings › Accessibility). Saved on this device, applied as
// data attributes on <html> that app.css reads alongside the matching OS settings, so either one
// turns a feature on. resources/views/app.blade.php applies them before first paint.

export type Choice<T extends string> = T;

export interface DisplayPrefs {
    theme: Choice<'system' | 'light' | 'dark'>;
    textSize: Choice<'default' | 'large' | 'larger'>;
    motion: Choice<'system' | 'reduce'>;
    transparency: Choice<'system' | 'reduce'>;
    contrast: Choice<'system' | 'more'>;
    underlineLinks: boolean;
}

export const defaultPrefs: DisplayPrefs = {
    theme: 'system',
    textSize: 'default',
    motion: 'system',
    transparency: 'system',
    contrast: 'system',
    underlineLinks: false,
};

const KEY = 'mv-display';
const THEME_KEY = 'mv-theme'; // kept separate: the header theme button and older sessions use it
export const PREFS_EVENT = 'mv-display-change';

export function readPrefs(): DisplayPrefs {
    if (typeof window === 'undefined') return defaultPrefs;
    let stored: Partial<DisplayPrefs> = {};
    let theme: string | null = null;
    try {
        stored = JSON.parse(localStorage.getItem(KEY) ?? '{}') as Partial<DisplayPrefs>;
        theme = localStorage.getItem(THEME_KEY);
    } catch {
        // Storage blocked or corrupt: fall back to defaults.
    }
    return { ...defaultPrefs, ...stored, theme: theme === 'light' || theme === 'dark' ? theme : 'system' };
}

/** Put the preferences on <html>. Mirrors the inline script in app.blade.php. */
export function applyPrefs(p: DisplayPrefs): void {
    const html = document.documentElement;
    const set = (name: string, value: string | null) => {
        if (value === null) delete html.dataset[name];
        else html.dataset[name] = value;
    };
    set('theme', p.theme === 'system' ? null : p.theme);
    set('textSize', p.textSize === 'default' ? null : p.textSize);
    set('motion', p.motion === 'reduce' ? 'reduce' : null);
    set('transparency', p.transparency === 'reduce' ? 'reduce' : null);
    set('contrast', p.contrast === 'more' ? 'more' : null);
    set('links', p.underlineLinks ? 'underline' : null);
}

export function savePrefs(p: DisplayPrefs): void {
    applyPrefs(p);
    try {
        const { theme, ...rest } = p;
        localStorage.setItem(KEY, JSON.stringify(rest));
        if (theme === 'system') localStorage.removeItem(THEME_KEY);
        else localStorage.setItem(THEME_KEY, theme);
    } catch {
        // Storage blocked: the choice still applies until the page reloads.
    }
    window.dispatchEvent(new CustomEvent(PREFS_EVENT));
}

/** The OS setting or the in-app choice, whichever asks for less motion. */
export function prefersReducedMotion(): boolean {
    if (typeof window === 'undefined') return false;
    return document.documentElement.dataset.motion === 'reduce' || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
