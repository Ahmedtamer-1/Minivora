import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/cn';

/** Content card (Figma: 28px radius, frosted white). */
export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('card', className)} {...props} />;
}

export function CardHeader({ title, description, action }: { title: ReactNode; description?: ReactNode; action?: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4 px-6 pb-2 pt-6">
            <div>
                <h2 className="font-display text-base font-normal">{title}</h2>
                {description ? <p className="mt-1 text-sm text-ink-muted">{description}</p> : null}
            </div>
            {action}
        </div>
    );
}

export function CardBody({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('px-6 py-5', className)} {...props} />;
}
