import { cn } from '@/lib/cn';

/**
 * Living background: soft, perfectly round glows that slowly drift, breathe and swap places,
 * so the page never feels frozen. Sized in vmax so they stay circles on any screen shape.
 * Decorative only; Reduce Motion freezes them (see app.css).
 */
export function AnimatedBackdrop({ variant = 'auth', className }: { variant?: 'auth' | 'dashboard'; className?: string }) {
    return (
        <div aria-hidden className={cn('mv-backdrop pointer-events-none fixed inset-0 -z-10 overflow-hidden', variant === 'dashboard' && 'mv-backdrop--soft', className)}>
            <div className="mv-orb mv-orb--1" />
            <div className="mv-orb mv-orb--2" />
            <div className="mv-orb mv-orb--3" />
            <div className="mv-orb mv-orb--4" />
            <div className="mv-orb mv-orb--5" />
            <div className="mv-sheen" />
            <div className="mv-grain" />
        </div>
    );
}
