import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Minivora';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent<{ default: ComponentType }>(`./pages/${name}.tsx`, import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        // Inertia swaps pages without reloading <html>, so keep lang/dir in step with the current locale
        // (e.g. signing in from an Arabic sign-in page into an English account).
        const sync = (p: Record<string, unknown>) => {
            if (typeof p.locale === 'string') document.documentElement.lang = p.locale;
            if (p.dir === 'rtl' || p.dir === 'ltr') document.documentElement.dir = p.dir;
        };
        sync(props.initialPage.props);
        router.on('navigate', (event) => sync(event.detail.page.props));

        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#1D7AA0' },
});
