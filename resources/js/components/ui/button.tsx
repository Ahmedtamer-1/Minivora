import { Link } from '@inertiajs/react';
import type { ButtonHTMLAttributes, ComponentProps, ReactNode } from 'react';
import { cn } from '@/lib/cn';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'sm' | 'md' | 'lg';

const base =
    'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors disabled:cursor-not-allowed disabled:bg-disabled disabled:text-disabled-fg';

const variants: Record<Variant, string> = {
    primary: 'bg-primary text-primary-fg hover:bg-primary-hover active:bg-primary-pressed',
    secondary: 'bg-secondary text-secondary-fg border border-line hover:bg-secondary-hover',
    ghost: 'text-ink hover:bg-secondary',
    danger: 'bg-danger-subtle text-danger hover:brightness-95',
};

const sizes: Record<Size, string> = {
    sm: 'h-8 px-3 text-sm',
    md: 'h-10 px-4 text-sm',
    lg: 'h-12 px-6 text-base',
};

export function buttonClasses(variant: Variant = 'primary', size: Size = 'md', className?: string): string {
    return cn(base, variants[variant], sizes[size], className);
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
}

export function Button({ variant = 'primary', size = 'md', className, type = 'button', ...props }: ButtonProps) {
    return <button type={type} className={buttonClasses(variant, size, className)} {...props} />;
}

interface ButtonLinkProps extends Omit<ComponentProps<typeof Link>, 'className' | 'size'> {
    variant?: Variant;
    size?: Size;
    className?: string;
    children: ReactNode;
}

export function ButtonLink({ variant = 'primary', size = 'md', className, ...props }: ButtonLinkProps) {
    return <Link className={buttonClasses(variant, size, className)} {...props} />;
}
