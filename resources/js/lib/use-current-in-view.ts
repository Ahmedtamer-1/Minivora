import { useLayoutEffect, type RefObject } from 'react';

/**
 * Keep the current item (aria-current="page") visible in a sideways-scrolling row of tabs, so
 * switching pages on a phone doesn't throw the row back to its start. Scrolls only the row,
 * never the page, and without animation.
 */
export function useCurrentInView(ref: RefObject<HTMLElement | null>): void {
    useLayoutEffect(() => {
        const row = ref.current;
        const current = row?.querySelector<HTMLElement>('[aria-current="page"]');
        if (!row || !current || row.scrollWidth <= row.clientWidth) return;
        const rowBox = row.getBoundingClientRect();
        const box = current.getBoundingClientRect();
        if (box.left < rowBox.left) row.scrollLeft -= rowBox.left - box.left + 16;
        else if (box.right > rowBox.right) row.scrollLeft += box.right - rowBox.right + 16;
    });
}
