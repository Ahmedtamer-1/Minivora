import { Link, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';
import { cleanPath, pageFor, sectionFor } from '@/lib/nav';
import { useCan } from '@/lib/permissions';
import { useCurrentInView } from '@/lib/use-current-in-view';

/**
 * Tabs for the pages inside the current section (Products · Categories · Inventory). Rendered by
 * the dashboard layout, so every section switches sub-pages the same way. Hidden when there is
 * only one page the member may open.
 */
export function SectionNav() {
    const t = useT();
    const can = useCan();
    const { url } = usePage();
    const path = cleanPath(url);
    const row = useRef<HTMLElement>(null);
    useCurrentInView(row);
    const section = sectionFor(path);
    if (!section?.pages) return null;

    const pages = section.pages.filter((p) => !p.permission || can(p.permission));
    if (pages.length < 2) return null;
    const current = pageFor(section, path);

    return (
        <nav ref={row} aria-label={t(`nav.${section.key}`)} className="-mx-1 mb-6 overflow-x-auto px-1 pb-1">
            <ul className="flex w-max gap-1 glass-strong rounded-full p-1 shadow-sm">
                {pages.map((p) => {
                    const Icon = p.icon;
                    const on = p === current;
                    return (
                        <li key={p.key}>
                            <Link
                                href={p.href}
                                prefetch
                                preserveScroll={false}
                                aria-current={on ? 'page' : undefined}
                                className={cn(
                                    'inline-flex h-10 items-center gap-2 rounded-full px-4 text-sm transition-colors duration-200',
                                    on ? 'bg-elevated font-medium text-ink shadow-sm' : 'text-ink-muted hover:text-ink',
                                )}
                            >
                                <Icon className="h-4 w-4" aria-hidden strokeWidth={1.75} />
                                {t(`nav.${section.key}_${p.key}`)}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
