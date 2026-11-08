// Browser built-in speech: speech-to-text (Web Speech recognition) and text-to-speech
// (speechSynthesis). Free, no keys, nothing leaves the app server. Quality and Arabic support
// depend on the browser and device, so every caller must handle "not supported".
import { useCallback, useEffect, useRef, useState } from 'react';
import type { Locale } from '@/types';

const LANG: Record<Locale, string> = { ar: 'ar-EG', en: 'en-US' };

/* ---------------- Speaking (text-to-speech) ---------------- */

function synth(): SpeechSynthesis | null {
    return typeof window !== 'undefined' && 'speechSynthesis' in window ? window.speechSynthesis : null;
}

/**
 * Best voice for a language: exact region first (ar-EG), then any voice of that language.
 * Within those, network/neural voices ("Google", "Natural", "Online", "Premium") sound far better
 * than the old offline ones, so they win.
 */
export function pickVoice(voices: SpeechSynthesisVoice[], lang: string): SpeechSynthesisVoice | null {
    const base = lang.split('-')[0].toLowerCase();
    const norm = (l: string) => l.replace('_', '-').toLowerCase();
    const same = voices.filter((v) => norm(v.lang).split('-')[0] === base);
    if (same.length === 0) return null;
    const score = (v: SpeechSynthesisVoice) =>
        (norm(v.lang) === lang.toLowerCase() ? 10 : 0) + (/google|natural|online|premium|enhanced|neural/i.test(v.name) ? 5 : 0) + (v.localService ? 0 : 1);
    return [...same].sort((a, b) => score(b) - score(a))[0];
}

/** Text a voice can read: no markdown symbols, links or emoji, split into sentence-sized pieces. */
export function speakableChunks(text: string, max = 180): string[] {
    const clean = text
        .replace(/https?:\/\/\S+/g, '')
        .replace(/[*_`#>|~]+/g, '')
        .replace(/\p{Extended_Pictographic}/gu, '')
        .replace(/\s+/g, ' ')
        .trim();
    if (!clean) return [];

    // Chrome stops reading long utterances after ~15 seconds, so queue short ones instead.
    const sentences = clean.match(/[^.!?؟۔\n]+[.!?؟۔]*/g) ?? [clean];
    const chunks: string[] = [];
    let current = '';
    for (const s of sentences.map((x) => x.trim()).filter(Boolean)) {
        if ((current + ' ' + s).trim().length > max && current) {
            chunks.push(current);
            current = s;
        } else {
            current = (current + ' ' + s).trim();
        }
        while (current.length > max) {
            const cut = current.lastIndexOf(' ', max);
            const at = cut > max / 2 ? cut : max;
            chunks.push(current.slice(0, at).trim());
            current = current.slice(at).trim();
        }
    }
    if (current) chunks.push(current);
    return chunks;
}

export interface SpeechOutput {
    /** The browser can speak at all. */
    supported: boolean;
    /** A voice for the dashboard language is installed. False on many devices for Arabic. */
    hasVoice: boolean;
    /** Id of the message being read, or null. */
    speakingId: number | string | null;
    speak: (text: string, id?: number | string) => void;
    stop: () => void;
}

export function useSpeechOutput(locale: Locale): SpeechOutput {
    const s = synth();
    const lang = LANG[locale];
    const [voice, setVoice] = useState<SpeechSynthesisVoice | null>(null);
    const [speakingId, setSpeakingId] = useState<number | string | null>(null);
    const run = useRef(0);

    // Voices load asynchronously in Chrome; listen until they arrive.
    useEffect(() => {
        if (!s) return;
        const load = () => setVoice(pickVoice(s.getVoices(), lang));
        load();
        s.addEventListener('voiceschanged', load);
        return () => s.removeEventListener('voiceschanged', load);
    }, [s, lang]);

    const stop = useCallback(() => {
        run.current++;
        s?.cancel();
        setSpeakingId(null);
    }, [s]);

    const speak = useCallback(
        (text: string, id: number | string = 'reply') => {
            if (!s || !voice) return;
            const chunks = speakableChunks(text);
            if (chunks.length === 0) return;
            s.cancel();
            const mine = ++run.current;
            setSpeakingId(id);
            chunks.forEach((chunk, i) => {
                const u = new SpeechSynthesisUtterance(chunk);
                u.voice = voice;
                u.lang = voice.lang;
                u.rate = 1.02;
                if (i === chunks.length - 1) {
                    u.onend = () => run.current === mine && setSpeakingId(null);
                }
                u.onerror = () => run.current === mine && setSpeakingId(null);
                s.speak(u);
            });
        },
        [s, voice],
    );

    useEffect(() => () => s?.cancel(), [s]);

    return { supported: s !== null, hasVoice: voice !== null, speakingId, speak, stop };
}

/* ---------------- Listening (speech-to-text) ---------------- */

interface RecognitionResult {
    isFinal: boolean;
    0: { transcript: string };
}
interface Recognition {
    lang: string;
    interimResults: boolean;
    continuous: boolean;
    onresult: ((e: { results: ArrayLike<RecognitionResult> }) => void) | null;
    onend: (() => void) | null;
    onerror: ((e: { error: string }) => void) | null;
    start(): void;
    stop(): void;
    abort(): void;
}
type RecognitionCtor = new () => Recognition;

function recognitionCtor(): RecognitionCtor | null {
    if (typeof window === 'undefined') return null;
    const w = window as unknown as { SpeechRecognition?: RecognitionCtor; webkitSpeechRecognition?: RecognitionCtor };
    return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null;
}

/** Why listening stopped, mapped to a translation key under "speech.". */
export type MicError = 'denied' | 'no_speech' | 'no_mic' | 'network' | 'failed';

function micError(code: string): MicError | null {
    switch (code) {
        case 'not-allowed':
        case 'service-not-allowed':
            return 'denied';
        case 'no-speech':
            return 'no_speech';
        case 'audio-capture':
            return 'no_mic';
        case 'network':
            return 'network';
        case 'aborted':
            return null;
        default:
            return 'failed';
    }
}

export interface SpeechInput {
    supported: boolean;
    listening: boolean;
    error: MicError | null;
    /** Start listening. `onText` gets the running transcript; `onDone` the final one. */
    start: (onText: (text: string) => void, onDone: (text: string) => void) => void;
    stop: () => void;
}

export function useSpeechInput(locale: Locale): SpeechInput {
    const Ctor = recognitionCtor();
    const [listening, setListening] = useState(false);
    const [error, setError] = useState<MicError | null>(null);
    const current = useRef<Recognition | null>(null);

    const stop = useCallback(() => current.current?.stop(), []);

    const start = useCallback(
        (onText: (text: string) => void, onDone: (text: string) => void) => {
            if (!Ctor) return;
            current.current?.abort();
            const r = new Ctor();
            r.lang = LANG[locale];
            r.interimResults = true;
            r.continuous = false;
            let transcript = '';
            r.onresult = (e) => {
                transcript = Array.from(e.results)
                    .map((res) => res[0].transcript)
                    .join('');
                onText(transcript);
            };
            r.onerror = (e) => setError(micError(e.error));
            r.onend = () => {
                setListening(false);
                current.current = null;
                if (transcript.trim()) onDone(transcript.trim());
            };
            current.current = r;
            setError(null);
            setListening(true);
            try {
                r.start();
            } catch {
                setListening(false);
                setError('failed');
            }
        },
        [Ctor, locale],
    );

    useEffect(() => () => current.current?.abort(), []);

    return { supported: Ctor !== null, listening, error, start, stop };
}
