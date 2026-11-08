import { router } from '@inertiajs/react';
import { cn } from '@/lib/cn';

export type OrbState = 'idle' | 'thinking' | 'speaking';

/** The AI team's face: a glassy sphere that breathes when idle and ripples while thinking or talking. */
export function AiOrb({ state = 'idle', className, id }: { state?: OrbState; className?: string; id?: string }) {
    return (
        <span id={id} aria-hidden data-state={state} className={cn('mv-ai-orb block shrink-0', className)}>
            <span className="mv-ai-rings">
                <span />
                <span />
                <span />
            </span>
        </span>
    );
}

/**
 * Open the full-screen AI view. The orb that was clicked is tagged for the page transition, so it
 * grows into the big orb on the AI screen instead of the page just swapping.
 */
export function openAi(source?: HTMLElement | null, question?: string) {
    const orb = source?.querySelector<HTMLElement>('.mv-ai-orb') ?? source;
    if (orb) orb.style.viewTransitionName = 'mv-ai-orb';
    router.visit(question ? `/ai?q=${encodeURIComponent(question)}` : '/ai', {
        onFinish: () => {
            if (orb) orb.style.viewTransitionName = '';
        },
    });
}
