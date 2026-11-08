import { useEffect, useRef, useState } from 'react';

const reduced = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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
        if (!active || reduced() || examples.length === 0) {
            setText(examples[0] ?? '');
            return;
        }
        let i = 0;
        let pos = 0;
        let deleting = false;
        let id = 0;
        const tick = () => {
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
