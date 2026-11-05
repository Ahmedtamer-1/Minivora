import { forwardRef, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

const control =
    'block w-full rounded-md border-line bg-elevated text-ink placeholder:text-ink-muted shadow-sm focus:border-line-focus focus:ring-1 focus:ring-line-focus disabled:bg-disabled disabled:text-disabled-fg';

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement> & { invalid?: boolean }>(
    function Input({ className, invalid, ...props }, ref) {
        return <input ref={ref} className={cn(control, 'h-10', invalid && 'border-danger', className)} aria-invalid={invalid || undefined} {...props} />;
    },
);

export function Textarea({ className, invalid, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement> & { invalid?: boolean }) {
    return <textarea className={cn(control, invalid && 'border-danger', className)} aria-invalid={invalid || undefined} {...props} />;
}

export function Select({ className, invalid, ...props }: SelectHTMLAttributes<HTMLSelectElement> & { invalid?: boolean }) {
    return <select className={cn(control, 'h-10 pe-9', invalid && 'border-danger', className)} aria-invalid={invalid || undefined} {...props} />;
}

interface FieldProps {
    label: ReactNode;
    htmlFor: string;
    error?: string;
    hint?: ReactNode;
    children: ReactNode;
    className?: string;
}

/** Label + control + hint or error, wired for screen readers. */
export function Field({ label, htmlFor, error, hint, children, className }: FieldProps) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <label htmlFor={htmlFor} className="block text-sm font-medium text-ink">
                {label}
            </label>
            {children}
            {error ? (
                <p id={`${htmlFor}-error`} role="alert" className="text-sm text-danger">
                    {error}
                </p>
            ) : hint ? (
                <p className="text-xs text-ink-muted">{hint}</p>
            ) : null}
        </div>
    );
}
