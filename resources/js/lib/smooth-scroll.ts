import Lenis from 'lenis';
import { router } from '@inertiajs/react';
import { prefersReducedMotion } from './display-prefs';

let lenis: Lenis | null = null;

/**
 * Weighted, eased page scrolling (the native bar is hidden in app.css). Wheel input glides to a stop
 * instead of jumping; touch keeps the phone's own scrolling. Off when the user asks for reduced motion.
 * Inner scroll areas opt out with `data-lenis-prevent`.
 */
export function startSmoothScroll(): void {
    if (typeof window === 'undefined' || prefersReducedMotion()) return;

    lenis = new Lenis({ lerp: 0.085, wheelMultiplier: 0.9, smoothWheel: true });
    const raf = (time: number) => {
        lenis?.raf(time);
        requestAnimationFrame(raf);
    };
    requestAnimationFrame(raf);

    // Inertia sets the scroll position itself on each visit (top, or kept): follow it without gliding.
    router.on('navigate', () => {
        requestAnimationFrame(() => {
            lenis?.resize();
            lenis?.scrollTo(window.scrollY, { immediate: true, force: true });
        });
    });
}

/** Turn smooth scrolling off for good (Reduce Motion switched on in Settings). */
export function stopSmoothScroll(): void {
    lenis?.destroy();
    lenis = null;
}

/** Pause page scrolling (e.g. while a full-screen view owns the wheel). */
export function setSmoothScrollPaused(paused: boolean): void {
    if (!lenis) return;
    if (paused) lenis.stop();
    else lenis.start();
}
