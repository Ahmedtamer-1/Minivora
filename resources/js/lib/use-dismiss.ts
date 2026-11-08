import { useEffect, type RefObject } from 'react';

/** Close a popover on outside click or Escape, and return focus to its trigger on Escape. */
export function useDismiss(ref: RefObject<HTMLElement | null>, open: boolean, close: () => void, trigger?: RefObject<HTMLElement | null>): void {
    useEffect(() => {
        if (!open) return;
        const onPointer = (e: MouseEvent) => {
            if (!ref.current?.contains(e.target as Node)) close();
        };
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                close();
                trigger?.current?.focus();
            }
        };
        document.addEventListener('mousedown', onPointer);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onPointer);
            document.removeEventListener('keydown', onKey);
        };
    }, [open, close, ref, trigger]);
}
