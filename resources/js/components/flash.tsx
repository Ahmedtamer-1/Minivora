import { usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

/** Toast for the session flash message, above the tab bar on phones. */
export function Flash() {
    const t = useT();
    const { flash } = usePage<SharedProps>().props;
    const [message, setMessage] = useState<{ text: string; error: boolean } | null>(null);

    useEffect(() => {
        const text = flash.error ?? flash.success;
        setMessage(text ? { text, error: flash.error !== null } : null);
        if (!text) return;
        const timer = setTimeout(() => setMessage(null), 5000);
        return () => clearTimeout(timer);
    }, [flash.success, flash.error]);

    if (!message) return null;
    const Icon = message.error ? AlertCircle : CheckCircle2;

    return (
        <div
            role={message.error ? 'alert' : 'status'}
            className="fixed bottom-24 start-1/2 z-50 flex max-w-[calc(100vw-2rem)] -translate-x-1/2 items-center gap-2 rounded-full bg-ink py-2 pe-2 ps-4 text-sm text-canvas shadow-lg rtl:translate-x-1/2 lg:bottom-6"
        >
            <Icon className="h-4 w-4 shrink-0" aria-hidden />
            <span>{message.text}</span>
            <button type="button" onClick={() => setMessage(null)} className="grid h-8 w-8 place-items-center rounded-full opacity-80 hover:bg-white/10 hover:opacity-100" aria-label={t('common.close')}>
                <X className="h-4 w-4" aria-hidden />
            </button>
        </div>
    );
}
