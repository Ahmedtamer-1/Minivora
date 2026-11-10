import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { startCalmWhenIdle } from './lib/calm';
import { prefersReducedMotion } from './lib/display-prefs';
import { cleanPath, sectionFor } from './lib/nav';
import { startSmoothScroll } from './lib/smooth-scroll';

/** True when the link stays inside the section the member is in now. */
function sameSection(href: string | URL): boolean {
    const from = sectionFor(cleanPath(window.location.pathname));
    const to = sectionFor(cleanPath(new URL(href, window.location.href).pathname));
    return from !== undefined && from === to;
}

const appName = import.meta.env.VITE_APP_NAME || 'Minivora';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent<{ default: ComponentType }>(`./pages/${name}.tsx`, import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        // Inertia swaps pages without reloading <html>, so keep lang/dir in step with the current locale
        // (e.g. signing in from an Arabic sign-in page into an English account).
        // Switching language redirects back to the same URL, which Inertia treats as a "replace" visit and
        // does not fire `navigate` for, so sync on every successful visit (and on back/forward via navigate).
        const sync = (p: Record<string, unknown>) => {
            const html = document.documentElement;
            if (typeof p.locale === 'string' && p.locale !== html.lang) {
                html.lang = p.locale;
                // Pages prefetched on hover were rendered in the old language: drop them.
                router.flushAll();
            }
            if ((p.dir === 'rtl' || p.dir === 'ltr') && p.dir !== html.dir) html.dir = p.dir;
        };
        sync(props.initialPage.props);
        router.on('success', (event) => sync(event.detail.page.props));
        router.on('navigate', (event) => sync(event.detail.page.props));

        createRoot(el).render(<App {...props} />);
        startSmoothScroll();
        startCalmWhenIdle();
    },
    defaults: {
        // Moving to another area cross-fades with the View Transitions API (see app.css). Moving within
        // one area (Settings pages, a section's tabs) swaps in place, so the frame stays put and the
        // click feels instant. Browsers without the API, and Reduce Motion, always get the plain swap.
        visitOptions: (href, options) => ({
            viewTransition: (options.method ?? 'get') === 'get' && !prefersReducedMotion() && !sameSection(href),
        }),
    },
    progress: { color: '#1D7AA0' },
});
