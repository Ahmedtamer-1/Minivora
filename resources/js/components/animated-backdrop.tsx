import { useRef, useState, type CSSProperties, type ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { useScrollParallax } from '@/lib/motion';

/** One parallax layer. depth = pixels moved per pixel scrolled (0.03 far away … 0.2 close). */
export function ParallaxLayer({ depth, children }: { depth: number; children: ReactNode }) {
    return (
        <div className="mv-par" style={{ '--mv-depth': depth } as CSSProperties}>
            {children}
        </div>
    );
}

/**
 * Living background: soft, perfectly round glows that slowly drift, breathe and swap places,
 * so the page never feels frozen. Sized in vmax so they stay circles on any screen shape.
 * Scrolling moves each glow at its own small speed (micro parallax), which gives the page depth.
 * Decorative only; Reduce Motion freezes them (see app.css).
 */
export function AnimatedBackdrop({ variant = 'auth', className, children }: { variant?: 'auth' | 'dashboard'; className?: string; children?: ReactNode }) {
    // Every page mounts its own layout, so the orbs would restart on each visit. Starting them at a
    // point taken from the wall clock keeps the motion continuous from one page to the next.
    const [clock] = useState(() => `${-((Date.now() / 1000) % 100000).toFixed(2)}s`);
    const root = useRef<HTMLDivElement>(null);
    useScrollParallax(root);

    return (
        <div
            ref={root}
            aria-hidden
            style={{ '--mv-clock': clock } as CSSProperties}
            className={cn('mv-backdrop pointer-events-none fixed inset-0 -z-10 overflow-hidden', variant === 'dashboard' && 'mv-backdrop--soft', className)}
        >
            <ParallaxLayer depth={0.03}>
                <div className="mv-sheen" />
            </ParallaxLayer>
            <ParallaxLayer depth={0.05}>
                <div className="mv-orb mv-orb--2" />
                <div className="mv-orb mv-orb--3" />
            </ParallaxLayer>
            <ParallaxLayer depth={0.08}>
                <div className="mv-orb mv-orb--1" />
            </ParallaxLayer>
            <ParallaxLayer depth={0.12}>
                <div className="mv-orb mv-orb--4" />
            </ParallaxLayer>
            <ParallaxLayer depth={0.16}>
                <div className="mv-orb mv-orb--5" />
            </ParallaxLayer>
            {children}
            <div className="mv-grain" />
        </div>
    );
}
