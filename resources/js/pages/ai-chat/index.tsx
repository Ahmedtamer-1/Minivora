// Full-screen AI team chat: no rail, just the orb, the conversation and the ask bar.
// Replies come from POST /ai/chat (store facts + a model when one is configured).
import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, Mic, MicOff, RotateCcw, SendHorizontal, Volume2, VolumeX } from 'lucide-react';
import { useCallback, useEffect, useLayoutEffect, useRef, useState, type FormEvent } from 'react';
import { AiOrb, type OrbState } from '@/components/ai-orb';
import { AnimatedBackdrop } from '@/components/animated-backdrop';
import { roundButton } from '@/components/preferences';
import { cn } from '@/lib/cn';
import { dayPart, useT } from '@/lib/i18n';
import { setSmoothScrollPaused } from '@/lib/smooth-scroll';
import type { SharedProps } from '@/types';

interface Action {
    label: string;
    href?: string;
    post?: string;
    data?: Record<string, string>;
}

interface Message {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    actions?: Action[];
    error?: boolean;
}

interface Props {
    connected: boolean;
    initialQuestion: string | null;
}

// Minimal Web Speech typings (Chrome/Edge/Safari expose it prefixed).
interface Recognition {
    lang: string;
    interimResults: boolean;
    onresult: ((e: { results: ArrayLike<ArrayLike<{ transcript: string }>> }) => void) | null;
    onend: (() => void) | null;
    onerror: (() => void) | null;
    start(): void;
    stop(): void;
}
type RecognitionCtor = new () => Recognition;

function speechRecognition(): RecognitionCtor | null {
    if (typeof window === 'undefined') return null;
    const w = window as unknown as { SpeechRecognition?: RecognitionCtor; webkitSpeechRecognition?: RecognitionCtor };
    return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null;
}

/** Reveal text a few characters per frame so replies read like they're being spoken. */
function Typewriter({ text, onDone, onTick }: { text: string; onDone: () => void; onTick: () => void }) {
    const [shown, setShown] = useState(0);
    const done = useRef(onDone);
    const tick = useRef(onTick);
    done.current = onDone;
    tick.current = onTick;

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setShown(text.length);
            done.current();
            return;
        }
        // ~70 characters a second, but never longer than 2.5s for a long answer.
        const perMs = Math.max(0.07, text.length / 2500);
        const start = performance.now();
        let raf = 0;
        const step = (now: number) => {
            const i = Math.min(text.length, Math.ceil((now - start) * perMs));
            setShown(i);
            tick.current();
            if (i < text.length) raf = requestAnimationFrame(step);
            else done.current();
        };
        raf = requestAnimationFrame(step);
        return () => cancelAnimationFrame(raf);
    }, [text]);

    return (
        <>
            <span aria-hidden>{text.slice(0, shown)}</span>
            <span className="sr-only">{text}</span>
        </>
    );
}

export default function AiChat({ connected, initialQuestion }: Props) {
    const t = useT();
    const { auth, locale } = usePage<SharedProps>().props;
    const firstName = (auth.user?.name ?? '').trim().split(/\s+/)[0] ?? '';
    const [messages, setMessages] = useState<Message[]>([]);
    const [typingId, setTypingId] = useState<number | null>(null);
    const [busy, setBusy] = useState(false);
    const [text, setText] = useState('');
    const [voice, setVoice] = useState(false);
    const [listening, setListening] = useState(false);
    const nextId = useRef(1);
    const scroller = useRef<HTMLDivElement>(null);
    const input = useRef<HTMLInputElement>(null);
    const recognition = useRef<Recognition | null>(null);
    const asked = useRef(false);

    const orbState: OrbState = busy ? 'thinking' : typingId !== null ? 'speaking' : 'idle';
    const started = messages.length > 0;

    // The page scroll is not used here; the conversation scrolls inside its own panel.
    useEffect(() => {
        setSmoothScrollPaused(true);
        return () => setSmoothScrollPaused(false);
    }, []);

    const toBottom = useCallback((smooth = true) => {
        const el = scroller.current;
        if (el) el.scrollTo({ top: el.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    }, []);

    useLayoutEffect(() => toBottom(), [messages.length, busy, toBottom]);

    const speak = useCallback(
        (reply: string) => {
            if (!voice || typeof window === 'undefined' || !('speechSynthesis' in window)) return;
            window.speechSynthesis.cancel();
            const u = new SpeechSynthesisUtterance(reply);
            u.lang = locale === 'ar' ? 'ar-EG' : 'en-US';
            u.rate = 1.03;
            window.speechSynthesis.speak(u);
        },
        [voice, locale],
    );

    const send = useCallback(
        async (raw: string) => {
            const message = raw.trim();
            if (!message || busy) return;
            const history = messages.filter((m) => !m.error).slice(-12).map(({ role, content }) => ({ role, content }));
            setMessages((m) => [...m, { id: nextId.current++, role: 'user', content: message }]);
            setText('');
            setBusy(true);
            try {
                const { data } = await axios.post<{ reply: string; actions: Action[] }>('/ai/chat', { message, history });
                const id = nextId.current++;
                setMessages((m) => [...m, { id, role: 'assistant', content: data.reply, actions: data.actions }]);
                setTypingId(id);
                speak(data.reply);
            } catch (e) {
                const serverMessage = axios.isAxiosError(e) && e.response?.status === 429 ? (e.response.data as { message?: string })?.message : null;
                setMessages((m) => [...m, { id: nextId.current++, role: 'assistant', content: serverMessage ?? t('ai.error'), error: true }]);
            } finally {
                setBusy(false);
                input.current?.focus();
            }
        },
        [busy, messages, speak, t],
    );

    // A question typed on the dashboard ("Ask your store") arrives as ?q= and is sent right away.
    useEffect(() => {
        if (initialQuestion && !asked.current) {
            asked.current = true;
            void send(initialQuestion);
        }
    }, [initialQuestion, send]);

    const close = useCallback(() => {
        window.speechSynthesis?.cancel();
        router.visit('/dashboard');
    }, []);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') close();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [close]);

    useEffect(() => () => window.speechSynthesis?.cancel(), []);

    const Recognition = speechRecognition();
    const toggleMic = () => {
        if (!Recognition) return;
        if (listening) {
            recognition.current?.stop();
            return;
        }
        const r = new Recognition();
        r.lang = locale === 'ar' ? 'ar-EG' : 'en-US';
        r.interimResults = true;
        let finalText = '';
        r.onresult = (e) => {
            finalText = Array.from(e.results as ArrayLike<ArrayLike<{ transcript: string }>>)
                .map((res) => res[0].transcript)
                .join('');
            setText(finalText);
        };
        r.onend = () => {
            setListening(false);
            if (finalText.trim()) void send(finalText);
        };
        r.onerror = () => setListening(false);
        recognition.current = r;
        setListening(true);
        r.start();
    };

    const runAction = (a: Action) => {
        if (a.href) router.visit(a.href);
        else if (a.post) router.post(a.post, a.data ?? {});
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        void send(text);
    };

    const suggestions = [t('ai.s_restock'), t('ai.s_next'), t('ai.s_products'), t('ai.s_best'), t('ai.s_add')];

    return (
        <div className="relative isolate flex h-[100dvh] flex-col overflow-hidden">
            <Head title={t('ai.title')} />
            <AnimatedBackdrop />

            <header className="mv-ai-enter flex items-center justify-between gap-3 px-4 py-4 sm:px-8 sm:py-6">
                <button type="button" onClick={close} className="glass-strong inline-flex h-11 shrink-0 items-center gap-2 rounded-full px-4 text-ink shadow-sm transition-colors hover:bg-elevated" aria-label={t('ai.back')}>
                    <ArrowLeft className="h-[18px] w-[18px] rtl:-scale-x-100" aria-hidden />
                    <span className="hidden text-sm sm:inline">{t('ai.back')}</span>
                </button>
                <p className="font-display text-base">{t('ai.title')}</p>
                <div className="flex items-center gap-2">
                    {started ? (
                        <button
                            type="button"
                            className={roundButton}
                            aria-label={t('ai.new_chat')}
                            title={t('ai.new_chat')}
                            onClick={() => {
                                window.speechSynthesis?.cancel();
                                setMessages([]);
                                setTypingId(null);
                            }}
                        >
                            <RotateCcw className="h-[18px] w-[18px]" aria-hidden />
                        </button>
                    ) : null}
                    {'speechSynthesis' in globalThis ? (
                        <button
                            type="button"
                            className={cn(roundButton, voice && 'bg-brand-accent text-white')}
                            aria-pressed={voice}
                            aria-label={voice ? t('ai.voice_off') : t('ai.voice_on')}
                            title={voice ? t('ai.voice_off') : t('ai.voice_on')}
                            onClick={() => {
                                if (voice) window.speechSynthesis.cancel();
                                setVoice((v) => !v);
                            }}
                        >
                            {voice ? <Volume2 className="h-[18px] w-[18px]" aria-hidden /> : <VolumeX className="h-[18px] w-[18px]" aria-hidden />}
                        </button>
                    ) : null}
                </div>
            </header>

            <div ref={scroller} data-lenis-prevent className="mv-noscrollbar flex-1 overflow-y-auto px-4 sm:px-8">
                <div className="mx-auto flex min-h-full w-full max-w-3xl flex-col">
                    {/* Orb: centre stage before the first question, then it settles at the top. */}
                    <div
                        className={cn(
                            'flex flex-col items-center text-center transition-all duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                            started ? 'pb-4 pt-2' : 'flex-1 justify-center pb-8',
                        )}
                    >
                        <AiOrb state={orbState} className={cn('vt-ai-orb transition-all duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]', started ? 'h-14 w-14' : 'h-36 w-36 sm:h-44 sm:w-44')} />
                        <div className={cn('grid transition-all duration-500', started ? 'grid-rows-[0fr] opacity-0' : 'mt-8 grid-rows-[1fr] opacity-100')}>
                            <div className="overflow-hidden">
                                <h1 className="mv-ai-enter text-[28px] font-light leading-tight tracking-[-0.02em] sm:text-[34px]">
                                    {t('ai.hello', { greeting: t(`ai.greeting_${dayPart()}`), name: firstName })}
                                </h1>
                                <p className="mv-ai-enter mx-auto mt-2 max-w-md text-sm text-ink-muted [animation-delay:80ms]">{t('ai.hello_sub')}</p>
                                <div className="mt-6 flex flex-wrap justify-center gap-2">
                                    {suggestions.map((s, i) => (
                                        <button
                                            key={s}
                                            type="button"
                                            onClick={() => void send(s)}
                                            style={{ animationDelay: `${150 + i * 60}ms` }}
                                            className="mv-pop glass-strong inline-flex h-10 items-center rounded-full px-4 text-sm text-ink shadow-sm transition-transform hover:-translate-y-0.5 hover:bg-elevated"
                                        >
                                            {s}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>

                    <ol className="flex flex-col gap-4 pb-6" aria-live="polite" aria-label={t('ai.title')}>
                        {messages.map((m) =>
                            m.role === 'user' ? (
                                <li key={m.id} className="mv-pop flex justify-end">
                                    <p className="max-w-[85%] whitespace-pre-wrap rounded-3xl rounded-ee-md bg-primary px-4 py-2.5 text-sm text-primary-fg shadow-md">
                                        <span className="sr-only">{t('ai.you')}: </span>
                                        {m.content}
                                    </p>
                                </li>
                            ) : (
                                <li key={m.id} className="mv-pop flex items-start gap-3">
                                    <AiOrb className="mt-1 h-7 w-7" state={typingId === m.id ? 'speaking' : 'idle'} />
                                    <div className="min-w-0 max-w-[85%]">
                                        <div className={cn('card whitespace-pre-wrap rounded-3xl rounded-ss-md px-4 py-3 text-sm leading-relaxed', m.error && 'text-danger')}>
                                            <span className="sr-only">{t('ai.assistant')}: </span>
                                            {typingId === m.id ? <Typewriter text={m.content} onTick={() => toBottom(false)} onDone={() => setTypingId(null)} /> : m.content}
                                        </div>
                                        {m.actions?.length && typingId !== m.id ? (
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {m.actions.map((a) => (
                                                    <button
                                                        key={a.label}
                                                        type="button"
                                                        onClick={() => runAction(a)}
                                                        className="mv-pop inline-flex h-9 items-center rounded-full bg-brand-accent px-4 text-caption text-white shadow-md transition-transform hover:-translate-y-0.5"
                                                    >
                                                        {a.label}
                                                    </button>
                                                ))}
                                            </div>
                                        ) : null}
                                    </div>
                                </li>
                            ),
                        )}
                        {busy ? (
                            <li className="mv-pop flex items-center gap-3 text-ink-muted">
                                <AiOrb className="h-7 w-7" state="thinking" />
                                <span className="card inline-flex items-center gap-2 rounded-3xl rounded-ss-md px-4 py-3">
                                    <span className="sr-only">{t('ai.thinking')}</span>
                                    <span className="mv-dots inline-flex gap-1" aria-hidden>
                                        <span />
                                        <span />
                                        <span />
                                    </span>
                                </span>
                            </li>
                        ) : null}
                    </ol>
                </div>
            </div>

            <div className="mv-ai-enter px-4 pb-4 pt-2 [animation-delay:120ms] sm:px-8 sm:pb-8">
                <form onSubmit={submit} className="mx-auto max-w-3xl">
                    <label htmlFor="ai-input" className="sr-only">
                        {t('ai.placeholder')}
                    </label>
                    <div className={cn('glass flex h-14 items-center gap-2 rounded-full pe-1.5 ps-5 transition-shadow', listening && 'ring-2 ring-brand-highlight')}>
                        <input
                            ref={input}
                            id="ai-input"
                            value={text}
                            onChange={(e) => setText(e.target.value)}
                            placeholder={listening ? t('ai.listening') : t('ai.placeholder')}
                            className="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-ink placeholder:text-ink-muted focus:ring-0"
                            maxLength={1000}
                            autoComplete="off"
                            autoFocus
                        />
                        {Recognition ? (
                            <button
                                type="button"
                                onClick={toggleMic}
                                className={cn('grid h-11 w-11 shrink-0 place-items-center rounded-full transition-colors', listening ? 'mv-pulse bg-brand-highlight text-[#0B1A26]' : 'text-ink-muted hover:bg-secondary hover:text-ink')}
                                aria-label={listening ? t('ai.mic_stop') : t('ai.mic')}
                                aria-pressed={listening}
                            >
                                {listening ? <MicOff className="h-[18px] w-[18px]" aria-hidden /> : <Mic className="h-[18px] w-[18px]" aria-hidden />}
                            </button>
                        ) : null}
                        <button
                            type="submit"
                            disabled={busy || !text.trim()}
                            className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-primary text-primary-fg transition-all hover:bg-primary-hover active:scale-90 disabled:opacity-50"
                            aria-label={t('ai.send')}
                        >
                            <SendHorizontal className="h-[18px] w-[18px] rtl:-scale-x-100" aria-hidden />
                        </button>
                    </div>
                    {!connected ? <p className="mt-2 text-center text-caption text-ink-muted">{t('ai.offline_note')}</p> : null}
                </form>
            </div>
        </div>
    );
}
