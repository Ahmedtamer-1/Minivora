import { Link } from '@inertiajs/react';

export function Logo({ href = '/' }: { href?: string }) {
    return (
        <Link href={href} className="inline-flex items-center gap-2 font-display text-lg font-semibold text-ink">
            <span aria-hidden className="grid h-8 w-8 place-items-center rounded-md bg-primary text-primary-fg">
                M
            </span>
            Minivora
        </Link>
    );
}
