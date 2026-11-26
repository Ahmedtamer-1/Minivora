// Storefront behaviour for builder sections. Plain DOM, no framework: every widget is set up by
// init(root) so the editor preview can swap one section's HTML and re-initialise just that part.

type Cleanup = () => void;
const cleanups = new WeakMap<Element, Cleanup[]>();
const body = document.body;
const isPreview = body.classList.contains('mv-preview');
const isAr = document.documentElement.lang === 'ar';
const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

document.documentElement.classList.add('mv-js');
body.classList.add('mv-js');

function onCleanup(el: Element, fn: Cleanup) {
    const list = cleanups.get(el) ?? [];
    list.push(fn);
    cleanups.set(el, list);
}

function each<T extends Element>(root: ParentNode, selector: string, fn: (el: T) => void) {
    if (root instanceof Element && root.matches(selector)) fn(root as T);
    root.querySelectorAll<T>(selector).forEach(fn);
}

function toast(message: string, link?: { href: string; label: string }) {
    const el = document.querySelector<HTMLElement>('.mv-toast');
    if (!el) return;
    el.textContent = message;
    if (link) {
        const a = document.createElement('a');
        a.href = link.href;
        a.textContent = link.label;
        el.appendChild(a);
    }
    el.hidden = false;
    window.clearTimeout(Number(el.dataset.timer));
    el.dataset.timer = String(window.setTimeout(() => (el.hidden = true), 3500));
}

async function send(url: string, method: string, data?: unknown) {
    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: data === undefined ? undefined : JSON.stringify(data),
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
        const errors = (json as { errors?: Record<string, string[]>; message?: string }).errors;
        throw new Error((errors && Object.values(errors).flat()[0]) || (json as { message?: string }).message || 'Error');
    }
    return json;
}

/* ── Reveal on scroll ─────────────────────────────── */
const revealer = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => entries.forEach((e) => {
        if (e.isIntersecting) {
            e.target.classList.add('is-in');
            revealer?.unobserve(e.target);
        }
    }), { rootMargin: '0px 0px -10% 0px' })
    : null;

/* ── Rotators: slideshow, announcement bar, testimonial carousel ── */
function rotator(container: HTMLElement, items: HTMLElement[], opts: { interval: number; autoplay: boolean; dots?: HTMLElement[]; prev?: HTMLElement | null; next?: HTMLElement | null }) {
    if (items.length === 0) return;
    let index = Math.max(0, items.findIndex((i) => i.classList.contains('is-active')));
    let timer = 0;
    const show = (i: number) => {
        index = (i + items.length) % items.length;
        items.forEach((item, n) => {
            item.classList.toggle('is-active', n === index);
            item.setAttribute('aria-hidden', n === index ? 'false' : 'true');
        });
        opts.dots?.forEach((d, n) => {
            d.classList.toggle('is-active', n === index);
            d.setAttribute('aria-current', n === index ? 'true' : 'false');
        });
    };
    const play = () => {
        window.clearInterval(timer);
        if (opts.autoplay && items.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            timer = window.setInterval(() => show(index + 1), Math.max(1500, opts.interval));
        }
    };
    show(index);
    play();
    opts.dots?.forEach((d, n) => d.addEventListener('click', () => { show(n); play(); }));
    opts.prev?.addEventListener('click', () => { show(index - 1); play(); });
    opts.next?.addEventListener('click', () => { show(index + 1); play(); });
    container.addEventListener('mouseenter', () => window.clearInterval(timer));
    container.addEventListener('mouseleave', play);
    onCleanup(container, () => window.clearInterval(timer));
}

/* ── Countdown ────────────────────────────────────── */
function countdown(el: HTMLElement) {
    const end = new Date(el.dataset.countdown ?? '').getTime();
    if (Number.isNaN(end)) {
        el.hidden = true;
        return;
    }
    const labels = isAr ? ['يوم', 'ساعة', 'دقيقة', 'ثانية'] : ['Days', 'Hrs', 'Min', 'Sec'];
    const tick = () => {
        const left = Math.max(0, end - Date.now());
        if (left === 0) {
            const expired = el.dataset.expired;
            if (expired) el.textContent = expired;
            window.clearInterval(timer);
            return;
        }
        const parts = [Math.floor(left / 864e5), Math.floor(left / 36e5) % 24, Math.floor(left / 6e4) % 60, Math.floor(left / 1e3) % 60];
        el.innerHTML = parts.map((p, i) => `<span><b>${String(p).padStart(2, '0')}</b><small>${labels[i]}</small></span>`).join('');
    };
    const timer = window.setInterval(tick, 1000);
    tick();
    onCleanup(el, () => window.clearInterval(timer));
}

/* ── Header: sticky/transparent state, drawer, search ── */
function header(el: HTMLElement) {
    const onScroll = () => el.classList.toggle('is-scrolled', window.scrollY > 40);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    onCleanup(el, () => window.removeEventListener('scroll', onScroll));

    const drawer = el.querySelector<HTMLElement>('.mv-drawer');
    el.querySelectorAll<HTMLElement>('[data-drawer-open]').forEach((b) => b.addEventListener('click', () => {
        drawer?.classList.add('is-open');
        b.setAttribute('aria-expanded', 'true');
        drawer?.querySelector<HTMLElement>('a, button')?.focus();
    }));
    el.querySelectorAll<HTMLElement>('[data-drawer-close]').forEach((b) => b.addEventListener('click', () => {
        drawer?.classList.remove('is-open');
        el.querySelector('[data-drawer-open]')?.setAttribute('aria-expanded', 'false');
    }));
    const search = el.querySelector<HTMLElement>('.mv-search-bar');
    el.querySelector<HTMLElement>('[data-search-toggle]')?.addEventListener('click', (e) => {
        search?.classList.toggle('is-open');
        (e.currentTarget as HTMLElement).setAttribute('aria-expanded', String(search?.classList.contains('is-open')));
        search?.querySelector<HTMLInputElement>('input')?.focus();
    });
}

/* ── Carousels with arrow buttons ─────────────────── */
function carouselNav(section: HTMLElement) {
    const track = section.querySelector<HTMLElement>('.mv-carousel');
    if (!track) return;
    const step = (dir: number) => {
        const rtl = getComputedStyle(track).direction === 'rtl';
        track.scrollBy({ left: dir * (rtl ? -1 : 1) * track.clientWidth * 0.8, behavior: 'smooth' });
    };
    section.querySelector('[data-carousel-prev]')?.addEventListener('click', () => step(-1));
    section.querySelector('[data-carousel-next]')?.addEventListener('click', () => step(1));
}

/* ── Product form: variants, quantity, add to cart ── */
function productForm(form: HTMLFormElement) {
    const variants = JSON.parse(form.dataset.variants ?? '[]') as { id: number; options: Record<string, string>; price: string; stock: number }[];
    const price = form.querySelector<HTMLElement>('[data-price]');
    const submit = form.querySelector<HTMLButtonElement>('[type="submit"]');
    const qty = form.querySelector<HTMLInputElement>('input[name="quantity"]');
    const variantInput = form.querySelector<HTMLInputElement>('input[name="variant_id"]');

    const sync = () => {
        if (variants.length === 0) return;
        const chosen: Record<string, string> = {};
        form.querySelectorAll<HTMLInputElement>('[data-option]:checked').forEach((i) => (chosen[i.dataset.option!] = i.value));
        const match = variants.find((v) => Object.entries(v.options).every(([k, val]) => chosen[k] === undefined || chosen[k] === val));
        if (variantInput) variantInput.value = match ? String(match.id) : '';
        if (match && price) price.textContent = match.price;
        if (submit) {
            submit.disabled = !match || match.stock <= 0;
            submit.textContent = !match ? submit.dataset.unavailable ?? '' : match.stock <= 0 ? submit.dataset.soldOut ?? '' : submit.dataset.add ?? '';
        }
    };
    form.addEventListener('change', sync);
    sync();

    form.querySelectorAll<HTMLButtonElement>('[data-qty]').forEach((b) => b.addEventListener('click', () => {
        if (!qty) return;
        qty.value = String(Math.max(1, Math.min(99, Number(qty.value || 1) + Number(b.dataset.qty))));
    }));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (isPreview) return toast(isAr ? 'المعاينة: السلة غير مفعّلة' : 'Preview: cart is disabled');
        submit?.setAttribute('disabled', '');
        try {
            const json = (await send(body.dataset.cartUrl!, 'POST', {
                product_id: Number(form.dataset.productId),
                variant_id: variantInput?.value ? Number(variantInput.value) : null,
                quantity: Number(qty?.value || 1),
            })) as { cart?: { item_count?: number; items?: unknown[] } };
            const count = json.cart?.item_count ?? json.cart?.items?.length;
            document.querySelectorAll<HTMLElement>('[data-cart-count]').forEach((c) => {
                c.textContent = String(count ?? Number(c.textContent || 0) + 1);
                c.hidden = false;
            });
            toast(isAr ? 'أضيف إلى السلة' : 'Added to cart', { href: body.dataset.cartPage!, label: isAr ? 'عرض السلة' : 'View cart' });
        } catch (err) {
            toast((err as Error).message);
        } finally {
            submit?.removeAttribute('disabled');
            sync();
        }
    });
}

/* ── Cart page lines ──────────────────────────────── */
function cartLine(line: HTMLElement) {
    const url = line.dataset.url!;
    line.querySelectorAll<HTMLButtonElement>('[data-line-qty]').forEach((b) => b.addEventListener('click', async () => {
        const next = Number(line.dataset.qty) + Number(b.dataset.lineQty);
        try {
            if (next <= 0) await send(url, 'DELETE');
            else await send(url, 'PUT', { quantity: next });
            window.location.reload();
        } catch (err) {
            toast((err as Error).message);
        }
    }));
    line.querySelector('[data-line-remove]')?.addEventListener('click', async () => {
        await send(url, 'DELETE').catch(() => undefined);
        window.location.reload();
    });
}

/* ── Newsletter ───────────────────────────────────── */
function newsletter(form: HTMLFormElement) {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (isPreview) return toast(isAr ? 'المعاينة: الاشتراك غير مفعّل' : 'Preview: sign-up is disabled');
        const email = (form.querySelector('input[type="email"]') as HTMLInputElement).value;
        try {
            await send(form.action, 'POST', { email, consent: true, locale: document.documentElement.lang });
            form.reset();
            toast(isAr ? 'شكراً! تم الاشتراك.' : "Thanks — you're subscribed.");
        } catch (err) {
            toast((err as Error).message);
        }
    });
}

/* ── Reels: play on hover / when visible on touch ─── */
function reel(video: HTMLVideoElement) {
    const card = video.closest('.mv-reel') ?? video;
    card.addEventListener('mouseenter', () => void video.play().catch(() => undefined));
    card.addEventListener('mouseleave', () => video.pause());
}

export function init(root: ParentNode = document) {
    each<HTMLElement>(root, '[data-animate]', (el) => (revealer ? revealer.observe(el) : el.classList.add('is-in')));
    each<HTMLElement>(root, '[data-rotator]', (el) => {
        const items = Array.from(el.querySelectorAll<HTMLElement>(':scope [data-rotator-item]'));
        rotator(el, items, {
            interval: Number(el.dataset.interval || 5000),
            autoplay: el.dataset.autoplay !== 'false' && !isPreview, // never move content away from someone editing it
            dots: Array.from(el.querySelectorAll<HTMLElement>('[data-rotator-dot]')),
            prev: el.querySelector<HTMLElement>('[data-rotator-prev]'),
            next: el.querySelector<HTMLElement>('[data-rotator-next]'),
        });
    });
    each<HTMLElement>(root, '[data-countdown]', countdown);
    each<HTMLElement>(root, '.mv-section--header', header);
    each<HTMLElement>(root, '[data-carousel]', carouselNav);
    each<HTMLFormElement>(root, 'form[data-product-form]', productForm);
    each<HTMLElement>(root, '[data-cart-line]', cartLine);
    each<HTMLFormElement>(root, 'form[data-newsletter]', newsletter);
    each<HTMLVideoElement>(root, 'video[data-hover-play]', reel);

    const announce = document.querySelector<HTMLElement>('.mv-section--announcement-bar');
    document.documentElement.style.setProperty('--announce-h', `${announce?.offsetHeight ?? 0}px`);
}

function destroy(el: Element) {
    [el, ...Array.from(el.querySelectorAll('*'))].forEach((node) => {
        cleanups.get(node)?.forEach((fn) => fn());
        cleanups.delete(node);
    });
}

init();

/* ── Editor preview bridge ────────────────────────── */
// Click selects; double-click edits text in place, swaps an image or opens the background colour;
// a floating toolbar on the selected section moves, duplicates, hides or deletes it.
if (isPreview && window.parent !== window) {
    const post = (msg: Record<string, unknown>) => window.parent.postMessage({ source: 'mv-preview', ...msg }, window.location.origin);
    let selected: string | null = null;
    let editing: { el: HTMLElement; original: string; section: string; block?: string; key: string } | null = null;

    const sectionEl = (id: string | null) => (id ? document.querySelector<HTMLElement>(`[data-mv-section="${CSS.escape(id)}"]`) : null);
    const blockOf = (el: HTMLElement | null) => el?.closest<HTMLElement>('[data-mv-block]')?.dataset.mvBlock;

    /* Toolbar */
    const icon = (d: string) => `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${d}</svg>`;
    const toolbar = document.createElement('div');
    toolbar.className = 'mv-edit-toolbar';
    toolbar.setAttribute('role', 'toolbar');
    const tools: [string, string, string][] = [
        ['up', isAr ? 'لأعلى' : 'Move up', icon('<path d="m18 15-6-6-6 6"/>')],
        ['down', isAr ? 'لأسفل' : 'Move down', icon('<path d="m6 9 6 6 6-6"/>')],
        ['duplicate', isAr ? 'تكرار' : 'Duplicate', icon('<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>')],
        ['hide', isAr ? 'إخفاء' : 'Hide', icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><path d="m3 3 18 18"/>')],
        ['delete', isAr ? 'حذف' : 'Delete', icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>')],
    ];
    toolbar.innerHTML = `<label class="mv-edit-toolbar__color" title="${isAr ? 'لون الخلفية' : 'Background colour'}"><span></span><input type="color" aria-label="${isAr ? 'لون الخلفية' : 'Background colour'}"></label>`
        + tools.map(([a, l, i]) => `<button type="button" data-tool="${a}" title="${l}" aria-label="${l}">${i}</button>`).join('');
    const colorInput = toolbar.querySelector('input') as HTMLInputElement;
    toolbar.addEventListener('click', (e) => {
        e.stopPropagation();
        const btn = (e.target as HTMLElement).closest<HTMLElement>('[data-tool]');
        if (btn && selected) post({ type: 'action', action: btn.dataset.tool, id: selected });
    });
    toolbar.addEventListener('dblclick', (e) => e.stopPropagation());
    colorInput.addEventListener('input', () => {
        if (!selected) return;
        const sec = sectionEl(selected);
        sec?.style.setProperty('--section-bg', colorInput.value);
        (toolbar.querySelector('.mv-edit-toolbar__color span') as HTMLElement).style.background = colorInput.value;
        post({ type: 'style', id: selected, key: 'background', value: colorInput.value });
    });

    const mark = () => {
        document.querySelectorAll('[data-mv-section]').forEach((s) => s.classList.toggle('mv-selected', (s as HTMLElement).dataset.mvSection === selected));
        const sec = sectionEl(selected);
        if (sec) {
            sec.appendChild(toolbar);
            const bg = getComputedStyle(sec).backgroundColor;
            const rgb = bg.match(/\d+/g);
            const hex = rgb && rgb.length >= 3 && !(rgb.length === 4 && rgb[3] === '0')
                ? '#' + rgb.slice(0, 3).map((n) => Number(n).toString(16).padStart(2, '0')).join('')
                : '#ffffff';
            colorInput.value = hex;
            (toolbar.querySelector('.mv-edit-toolbar__color span') as HTMLElement).style.background = hex;
        } else {
            toolbar.remove();
        }
    };

    /* Inline text editing */
    // The typed characters, not innerText: innerText applies CSS text-transform (uppercase headings).
    const readText = (el: HTMLElement) => {
        const clone = el.cloneNode(true) as HTMLElement;
        clone.querySelectorAll('br').forEach((br) => br.replaceWith('\n'));
        clone.querySelectorAll('div, p').forEach((block, i) => i > 0 || block !== clone.firstElementChild ? block.prepend('\n') : undefined);
        return (clone.textContent ?? '').replace(/\u00a0/g, ' ').replace(/\n$/, '');
    };
    const finishEdit = (cancel = false) => {
        if (!editing) return;
        const { el, original, section, block, key } = editing;
        editing = null;
        if (cancel) {
            el.textContent = original;
            post({ type: 'edit', id: section, blockId: block, key, value: original });
        }
        el.removeAttribute('contenteditable');
        el.classList.remove('mv-editing');
        post({ type: 'commit', id: section });
    };

    const startEdit = (el: HTMLElement) => {
        const section = el.closest<HTMLElement>('[data-mv-section]')?.dataset.mvSection;
        if (!section) return;
        finishEdit();
        editing = { el, original: readText(el), section, block: el.dataset.mvBlock, key: el.dataset.mvField! };
        try {
            el.contentEditable = 'plaintext-only';
        } catch {
            el.contentEditable = 'true';
        }
        if (el.contentEditable !== 'plaintext-only') el.contentEditable = 'true';
        el.classList.add('mv-editing');
        el.focus();
        const range = document.createRange();
        range.selectNodeContents(el);
        const sel = window.getSelection();
        sel?.removeAllRanges();
        sel?.addRange(range);
    };

    document.addEventListener('input', (e) => {
        if (!editing || e.target !== editing.el) return;
        post({ type: 'edit', id: editing.section, blockId: editing.block, key: editing.key, value: readText(editing.el) });
    });
    document.addEventListener('keydown', (e) => {
        if (!editing) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            finishEdit(true);
        } else if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            finishEdit();
        }
    });
    document.addEventListener('focusout', (e) => {
        if (editing && e.target === editing.el) finishEdit();
    });
    // Paste as plain text.
    document.addEventListener('paste', (e) => {
        if (!editing) return;
        e.preventDefault();
        document.execCommand('insertText', false, e.clipboardData?.getData('text/plain') ?? '');
    });

    document.addEventListener('click', (e) => {
        const target = e.target as HTMLElement;
        if (editing && editing.el.contains(target)) return;
        const link = target.closest('a');
        if (link && !link.hasAttribute('data-allow-preview')) e.preventDefault();
        const section = target.closest<HTMLElement>('[data-mv-section]');
        if (section) {
            selected = section.dataset.mvSection ?? null;
            mark();
            const field = target.closest<HTMLElement>('[data-mv-field]');
            post({ type: 'select', id: selected, blockId: blockOf(target), field: field?.dataset.mvField });
        }
    }, true);

    document.addEventListener('dblclick', (e) => {
        const target = e.target as HTMLElement;
        const section = target.closest<HTMLElement>('[data-mv-section]');
        if (!section) return;
        e.preventDefault();
        const field = target.closest<HTMLElement>('[data-mv-field]');
        if (field && section.contains(field)) {
            startEdit(field);
            return;
        }
        const image = target.closest<HTMLElement>('[data-mv-image]');
        if (image && section.contains(image)) {
            post({ type: 'pick-image', id: section.dataset.mvSection, blockId: image.dataset.mvBlock, key: image.dataset.mvImage });
            return;
        }
        selected = section.dataset.mvSection ?? null;
        mark();
        colorInput.click();
    });
    document.addEventListener('submit', (e) => e.preventDefault(), true);

    window.addEventListener('message', (e) => {
        if (e.origin !== window.location.origin || e.data?.source !== 'mv-editor') return;
        const { type, id, html } = e.data as { type: string; id?: string; html?: string };
        if (type === 'select') {
            selected = id ?? null;
            mark();
            sectionEl(selected)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        if (type === 'replace' && id && typeof html === 'string') {
            if (editing && editing.section === id) return; // never pull text out from under the cursor
            const old = sectionEl(id);
            if (!old) return;
            // A section is followed by its optional scoped <style>; replace both.
            const next = old.nextElementSibling;
            if (next?.tagName === 'STYLE') next.remove();
            const prevAnchor = old.previousElementSibling;
            if (prevAnchor?.classList.contains('mv-anchor')) prevAnchor.remove();
            destroy(old);
            const tpl = document.createElement('template');
            tpl.innerHTML = html;
            const nodes = Array.from(tpl.content.childNodes);
            old.replaceWith(...nodes);
            nodes.forEach((n) => n instanceof Element && init(n));
            nodes.forEach((n) => n instanceof Element && n.querySelectorAll('[data-animate]').forEach((a) => a.classList.add('is-in')));
            mark();
        }
        if (type === 'theme-css' && typeof html === 'string') {
            let style = document.getElementById('mv-theme-css') as HTMLStyleElement | null;
            if (!style) {
                style = document.createElement('style');
                style.id = 'mv-theme-css';
                document.head.appendChild(style);
            }
            style.textContent = html;
        }
    });

    // Keep the scroll position across full reloads after structural edits.
    const key = `mv-preview-scroll:${window.location.pathname}`;
    try {
        const y = Number(sessionStorage.getItem(key) || 0);
        if (y) window.scrollTo(0, y);
    } catch { /* storage unavailable */ }
    window.addEventListener('beforeunload', () => {
        try { sessionStorage.setItem(key, String(window.scrollY)); } catch { /* ignore */ }
    });
    document.querySelectorAll('[data-animate]').forEach((a) => a.classList.add('is-in'));
    post({ type: 'ready' });
}
