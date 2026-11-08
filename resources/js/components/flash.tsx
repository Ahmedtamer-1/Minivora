import { usePage } from '@inertiajs/react';
import { CheckCircle2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { SharedProps } from '@/types';

/** Toast for the session flash message. */
export function Flash() {
    const { flash } = usePage<SharedProps>().props;
    const [message, setMessage] = useState<string | null>(flash.success);

    useEffect(() => {
        setMessage(flash.success);
        if (!flash.success) return;
        const timer = setTimeout(() => setMessage(null), 4000);
        return () => clearTimeout(timer);
    }, [flash.success]);

    if (!message) return null;

    return (
        <div role="status" className="fixed bottom-4 start-1/2 z-50 flex -translate-x-1/2 items-center gap-2 rounded-md bg-ink px-4 py-3 text-sm text-canvas shadow-lg rtl:translate-x-1/2">
            <CheckCircle2 className="h-4 w-4" aria-hidden />
            <span>{message}</span>
            <button type="button" onClick={() => setMessage(null)} className="ms-2 opacity-70 hover:opacity-100" aria-label="Close">
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}
