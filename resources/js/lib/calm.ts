// Calm when idle: decorative loops (the drifting backdrop, the AI orb's swirl, pulsing dots) keep
// the browser repainting every frame, which on an ordinary laptop is most of the page's CPU. They
// run while someone is using the page and pause a few seconds after the last input, or whenever
// the window is in the background. Any pointer, key, wheel or touch wakes them again. app.css reads
// html[data-calm] and sets animation-play-state: paused, so nothing jumps: motion resumes where it stopped.

const IDLE_MS = 6000;
let timer = 0;

function setCalm(on: boolean): void {
    const html = document.documentElement;
    if (on) html.dataset.calm = '';
    else delete html.dataset.calm;
}

function wake(): void {
    if (document.documentElement.dataset.calm !== undefined) setCalm(false);
    window.clearTimeout(timer);
    timer = window.setTimeout(() => setCalm(true), IDLE_MS);
}

export function startCalmWhenIdle(): void {
    if (typeof window === 'undefined') return;
    for (const type of ['pointermove', 'pointerdown', 'keydown', 'wheel', 'touchstart', 'scroll'] as const) {
        window.addEventListener(type, wake, { passive: true });
    }
    window.addEventListener('blur', () => setCalm(true));
    window.addEventListener('focus', wake);
    document.addEventListener('visibilitychange', () => (document.hidden ? setCalm(true) : wake()));
    wake();
}
