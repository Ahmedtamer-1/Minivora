import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

/** Frosted glass panel (glass/fill, glass/stroke tokens). Place on an `aurora` background. */
export function GlassPanel({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('glass rounded-xl', className)} {...props} />;
}
