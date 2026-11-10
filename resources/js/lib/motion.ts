import { useEffect, useRef, useState } from 'react';

import { liteEffects, prefersReducedMotion as reduced } from './display-prefs';

/** False on the first paint, true right after: lets bars and rings grow from zero to their value. */
export function useAppeared(delay = 80): boolean {
    const [on, setOn] = useState(false);
    useEffect(() => {
        const id = window.setTimeout(() => setOn(true), reduced() ? 0 : delay);
        return () => window.clearTimeout(id);
    }, [delay]);
    return on;
}

/** Counts from the previous value (0 at first) up to `target` with an ease-out. */
export function useCountUp(target: number, duration = 1100): number {
    const [value, setValue] = useState(reduced() ? target : 0);
    const from = useRef(0);

    useEffect(() => {
        if (reduced()) {
            setValue(target);
            return;
        }
        const start = performance.now();
        const begin = from.current;
        let raf = 0;
        const step = (now: number) => {
            const p = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - p, 3);
            setValue(begin + (target - begin) * eased);
            if (p < 1) raf = requestAnimationFrame(step);
            else from.current = target;
        };
        raf = requestAnimationFrame(step);
        return () => cancelAnimationFrame(raf);
    }, [target, duration]);

    return value;
}

/** Types each example in turn, holds it, erases it: a placeholder that shows what you can ask. */
export function useTypedPlaceholder(examples: string[], active = true): string {
    const [text, setText] = useState(examples[0] ?? '');
    const key = examples.join('|');

    useEffect(() => {
        if (!active || reduced() || liteEffects() || examples.length === 0) {
            setText(examples[0] ?? '');
            return;
        }
        let i = 0;
        let pos = 0;
        let deleting = false;
        let id = 0;
        const tick = () => {
            // Asleep while the page is calm (lib/calm.ts): no re-render every few milliseconds.
            if (document.documentElement.dataset.calm !== undefined) {
                id = window.setTimeout(tick, 1000);
                return;
            }
            const word = examples[i];
            if (!deleting) {
                pos++;
                setText(word.slice(0, pos));
                if (pos >= word.length) {
                    deleting = true;
                    id = window.setTimeout(tick, 2200);
                    return;
                }
                id = window.setTimeout(tick, 45);
            } else {
                pos = Math.max(0, pos - 2);
                setText(word.slice(0, pos));
                if (pos === 0) {
                    deleting = false;
                    i = (i + 1) % examples.length;
                    id = window.setTimeout(tick, 350);
                    return;
                }
                id = window.setTimeout(tick, 22);
            }
        };
        id = window.setTimeout(tick, 600);
        return () => window.clearTimeout(id);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key, active]);

    return text;
}

/**
 * Scroll parallax: writes the page's scroll offset to `--mv-scroll` on the element each frame it
 * changes. Layers inside move by `--mv-scroll × --mv-depth` (see .mv-par in app.css), so deeper
 * layers drift less than nearer ones. With smooth scrolling on, the offset itself is eased, so the
 * layers glide. Off for Reduce Motion.
 */
export function useScrollParallax<T extends HTMLElement>(ref: React.RefObject<T | null>): void {
    useEffect(() => {
        const el = ref.current;
        if (!el || reduced() || liteEffects()) return;
        let raf = 0;
        let last = -1;
        const apply = () => {
            raf = 0;
            const y = Math.round(window.scrollY);
            if (y === last) return;
            last = y;
            el.style.setProperty('--mv-scroll', String(y));
        };
        const onScroll = () => {
            if (!raf) raf = requestAnimationFrame(apply);
        };
        apply();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => {
            window.removeEventListener('scroll', onScroll);
            if (raf) cancelAnimationFrame(raf);
        };
    }, [ref]);
}
