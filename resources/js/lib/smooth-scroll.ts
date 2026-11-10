import Lenis from 'lenis';
import { router } from '@inertiajs/react';
import { liteEffects, prefersReducedMotion } from './display-prefs';

let lenis: Lenis | null = null;

/**
 * Weighted, eased page scrolling (the native bar is hidden in app.css). Wheel input glides to a stop
 * instead of jumping; touch keeps the phone's own scrolling. Off when the user asks for reduced motion.
 * Inner scroll areas opt out with `data-lenis-prevent`.
 */
export function startSmoothScroll(): void {
    if (typeof window === 'undefined' || prefersReducedMotion() || liteEffects()) return;

    lenis = new Lenis({ lerp: 0.085, wheelMultiplier: 0.9, smoothWheel: true });

    // The animation loop runs only while a scroll is gliding, then sleeps until the next wheel,
    // key or touch, so an idle page costs nothing.
    let running = false;
    let quiet = 0;
    const raf = (time: number) => {
        if (!lenis) {
            running = false;
            return;
        }
        lenis.raf(time);
        quiet = lenis.isScrolling ? 0 : quiet + 1;
        if (quiet < 12) requestAnimationFrame(raf);
        else running = false;
    };
    const wake = () => {
        quiet = 0;
        if (!running && lenis) {
            running = true;
            requestAnimationFrame(raf);
        }
    };
    for (const type of ['wheel', 'touchstart', 'keydown', 'pointerdown'] as const) window.addEventListener(type, wake, { passive: true });
    lenis.on('scroll', wake);
    wake();

    // Inertia sets the scroll position itself on each visit (top, or kept): follow it without gliding.
    router.on('navigate', () => {
        requestAnimationFrame(() => {
            lenis?.resize();
            lenis?.scrollTo(window.scrollY, { immediate: true, force: true });
            wake();
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
