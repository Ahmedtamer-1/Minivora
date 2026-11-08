import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

/** Frosted glass for the floating controls layer (rail, menus, sign-in panel), not for content cards. */
export function GlassPanel({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('glass rounded-2xl', className)} {...props} />;
}
