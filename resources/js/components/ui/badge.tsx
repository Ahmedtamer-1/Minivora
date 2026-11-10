import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral' | 'highlight' | 'glass';

const tones: Record<Tone, string> = {
    success: 'bg-success-subtle text-success',
    warning: 'bg-warning-subtle text-warning',
    danger: 'bg-danger-subtle text-danger',
    info: 'bg-info-subtle text-info',
    neutral: 'bg-secondary text-ink-muted',
    highlight: 'bg-brand-highlight text-[#0B1A26]',
    glass: 'glass-strong text-ink',
};

/** Figma "Chip": 26px tall, Outfit Medium 13, pill. Sentence case, so Arabic is never letter-spaced. */
export function Badge({ tone = 'neutral', children, className }: { tone?: Tone; children: ReactNode; className?: string }) {
    return (
        <span className={cn('inline-flex h-[26px] shrink-0 items-center gap-1 rounded-full px-2 text-caption', tones[tone], className)}>
            {children}
        </span>
    );
}

/** Figma "Minivora/Label": 11px uppercase with tracking in Latin; plain 12px in Arabic. */
export function Label({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <span className={cn('inline-flex items-center rounded-full font-display text-[0.6875rem] font-semibold leading-none ltr:uppercase ltr:tracking-[0.16em] rtl:text-xs', className)}>
            {children}
        </span>
    );
}
