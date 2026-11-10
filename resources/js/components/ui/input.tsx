import { Children, cloneElement, forwardRef, isValidElement, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

const control =
    'block w-full rounded-lg border-line-control bg-elevated text-sm text-ink placeholder:text-ink-muted focus:border-line-focus focus:ring-1 focus:ring-line-focus disabled:bg-disabled disabled:text-disabled-fg';

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

/**
 * Label + control + hint or error, wired for screen readers: the control is described by the hint
 * or error (aria-describedby), and an error also marks it invalid, so the danger border and
 * aria-invalid never depend on each page remembering to pass `invalid`.
 */
export function Field({ label, htmlFor, error, hint, children, className }: FieldProps) {
    const errorId = `${htmlFor}-error`;
    const hintId = `${htmlFor}-hint`;
    const describedBy = error ? errorId : hint ? hintId : undefined;

    const only = Children.count(children) === 1 ? Children.only(children) : null;
    const control =
        describedBy && isValidElement<{ 'aria-describedby'?: string; invalid?: boolean; 'aria-invalid'?: boolean }>(only)
            ? cloneElement(only, {
                  'aria-describedby': [only.props['aria-describedby'], describedBy].filter(Boolean).join(' '),
                  ...(error ? (isControl(only.type) ? { invalid: true } : { 'aria-invalid': true }) : {}),
              })
            : children;

    return (
        <div className={cn('space-y-1.5', className)}>
            <label htmlFor={htmlFor} className="block text-caption text-ink">
                {label}
            </label>
            {control}
            {error ? (
                <p id={errorId} role="alert" className="text-sm text-danger">
                    {error}
                </p>
            ) : hint ? (
                <p id={hintId} className="text-xs text-ink-muted">
                    {hint}
                </p>
            ) : null}
        </div>
    );
}

const isControl = (type: unknown) => type === Input || type === Textarea || type === Select;

/**
 * Checkbox with a visible tick in both themes: brand-blue fill and a white check when on,
 * an outlined box when off. The real input stays in the DOM for keyboard and screen readers.
 */
export function Checkbox({ label, className, ...props }: Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & { label: ReactNode }) {
    return (
        <label className={cn('group inline-flex cursor-pointer select-none items-center gap-2.5 text-sm text-ink', className)}>
            <span className="relative inline-flex h-5 w-5 shrink-0">
                <input type="checkbox" className="peer absolute inset-0 m-0 h-full w-full cursor-pointer appearance-none opacity-0" {...props} />
                <span className="pointer-events-none absolute inset-0 rounded-md border-[1.5px] border-line-control bg-elevated transition-all duration-200 group-hover:border-line-focus peer-checked:scale-100 peer-checked:border-brand-accent peer-checked:bg-brand-accent peer-focus-visible:ring-2 peer-focus-visible:ring-line-focus peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-canvas peer-active:scale-90" />
                <svg
                    viewBox="0 0 16 16"
                    fill="none"
                    aria-hidden
                    className="pointer-events-none absolute inset-0 m-auto h-3.5 w-3.5 scale-50 text-white opacity-0 transition-all duration-200 peer-checked:scale-100 peer-checked:opacity-100"
                >
                    <path d="M3.5 8.5l3 3 6-7" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </span>
            {label}
        </label>
    );
}
