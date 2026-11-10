/* Minivora presentation runtime. No dependencies.
   Deck mode: ←/→, Space, PgUp/PgDn, Home/End, swipe, click; F = fullscreen; T = theme; D = document view;
   #5 in the URL opens slide 5. Doc mode: sections reveal as they scroll in.
   Mirrors the app's motion helpers (lib/motion.ts): count-up with cubic ease-out, bars/rings growing
   from zero, everything off under Reduce Motion. */
(function () {
    'use strict';
    var html = document.documentElement;
    var body = document.body;
    // ?mode=doc / ?mode=deck overrides the mode the file was built with (D toggles it).
    var forced = new URLSearchParams(location.search).get('mode');
    if (forced === 'doc' || forced === 'deck') {
        body.classList.remove('mv-mode-doc', 'mv-mode-deck');
        body.classList.add('mv-mode-' + forced);
    }
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var stat = html.classList.contains('mv-static');
    var slides = Array.prototype.slice.call(document.querySelectorAll('.mv-slide'));
    var stage = document.querySelector('.mv-stage');
    var bar = document.querySelector('.mv-progress > span');
    var backdrop = document.querySelector('.mv-backdrop');
    var current = -1;

    /* ---- Theme: saved choice, else the OS; same key as the app ---- */
    try {
        var saved = localStorage.getItem('mv-theme');
        if (saved && !html.dataset.theme) html.dataset.theme = saved;
    } catch (e) {}
    function toggleTheme() {
        var dark = html.dataset.theme ? html.dataset.theme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        html.dataset.theme = dark ? 'light' : 'dark';
        try { localStorage.setItem('mv-theme', html.dataset.theme); } catch (e) {}
    }

    /* ---- Split .mv-words titles into animated words ---- */
    Array.prototype.forEach.call(document.querySelectorAll('.mv-words'), function (el) {
        if (el.dataset.split) return;
        el.dataset.split = '1';
        var n = 0;
        (function walk(node) {
            Array.prototype.slice.call(node.childNodes).forEach(function (child) {
                if (child.nodeType === 3) {
                    var frag = document.createDocumentFragment();
                    child.textContent.split(/(\s+)/).forEach(function (part) {
                        if (!part) return;
                        if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(part)); return; }
                        var s = document.createElement('span');
                        s.className = 'w';
                        s.style.setProperty('--w', n++);
                        s.textContent = part;
                        frag.appendChild(s);
                    });
                    node.replaceChild(frag, child);
                } else if (child.nodeType === 1 && !child.classList.contains('w')) {
                    walk(child);
                }
            });
        })(el);
    });

    /* ---- Auto-number [data-anim] children that have no --i, in document order per slide ---- */
    slides.forEach(function (s) {
        var i = 0;
        Array.prototype.forEach.call(s.querySelectorAll('[data-anim]'), function (el) {
            if (!el.style.getPropertyValue('--i')) el.style.setProperty('--i', i);
            i++;
        });
        Array.prototype.forEach.call(s.querySelectorAll('.mv-bars'), function (b) {
            Array.prototype.forEach.call(b.querySelectorAll('.mv-bar-col'), function (c, k) { c.style.setProperty('--i', k); });
        });
    });

    /* ---- Rings: compute circumference and offset from data-value (0..100) ---- */
    Array.prototype.forEach.call(document.querySelectorAll('.mv-ring'), function (ring) {
        var fg = ring.querySelector('.mv-ring-fg');
        if (!fg) return;
        var r = parseFloat(fg.getAttribute('r'));
        var c = 2 * Math.PI * r;
        var v = Math.max(0, Math.min(100, parseFloat(ring.dataset.value || '0')));
        ring.style.setProperty('--c', c.toFixed(2));
        ring.style.setProperty('--off', (c * (1 - v / 100)).toFixed(2));
    });

    /* ---- Count-up numbers: <span data-count="1234.5" data-decimals="1" data-prefix="$" data-suffix="%"> ---- */
    function fmt(el, v) {
        var d = parseInt(el.dataset.decimals || '0', 10);
        var locale = el.dataset.locale || html.lang || 'en';
        var s;
        try { s = v.toLocaleString(locale.indexOf('ar') === 0 ? 'en' : locale, { minimumFractionDigits: d, maximumFractionDigits: d }); }
        catch (e) { s = v.toFixed(d); }
        return (el.dataset.prefix || '') + s + (el.dataset.suffix || '');
    }
    function countUp(root) {
        Array.prototype.forEach.call(root.querySelectorAll('[data-count]'), function (el) {
            var target = parseFloat(el.dataset.count);
            if (isNaN(target)) return;
            if (reduced || stat) { el.textContent = fmt(el, target); return; }
            var dur = parseInt(el.dataset.duration || '1400', 10);
            var delay = parseInt(el.dataset.delay || '250', 10);
            var start = null;
            el.textContent = fmt(el, 0);
            if (el._raf) cancelAnimationFrame(el._raf);
            setTimeout(function () {
                function step(now) {
                    if (start === null) start = now;
                    var p = Math.min(1, (now - start) / dur);
                    var eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = fmt(el, target * eased);
                    if (p < 1) el._raf = requestAnimationFrame(step);
                }
                el._raf = requestAnimationFrame(step);
            }, delay);
        });
    }
    function settle(root) {
        Array.prototype.forEach.call(root.querySelectorAll('[data-count]'), function (el) {
            var t = parseFloat(el.dataset.count);
            if (!isNaN(t)) el.textContent = fmt(el, t);
        });
    }

    /* ---- Doc mode ---- */
    if (body.classList.contains('mv-mode-doc')) {
        if (stat || !('IntersectionObserver' in window)) {
            slides.forEach(function (s) { s.classList.add('is-active'); settle(s); });
        } else {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting && !en.target.classList.contains('is-active')) {
                        en.target.classList.add('is-active');
                        countUp(en.target);
                        io.unobserve(en.target);
                    }
                });
            }, { threshold: 0.15 });
            slides.forEach(function (s) { io.observe(s); });
        }
        bindCommon();
        var deckBtn = document.querySelector('[data-mv-action="deck"]');
        if (deckBtn) deckBtn.addEventListener('click', function () { switchMode('deck'); });
        return;
    }

    /* ---- Deck mode ---- */
    function fit() {
        if (!stage) return;
        var s = Math.min(window.innerWidth / 1600, window.innerHeight / 900);
        stage.style.setProperty('--mv-scale', s.toFixed(4));
    }
    window.addEventListener('resize', fit);
    fit();

    function go(n, instant) {
        if (!slides.length) return;
        n = Math.max(0, Math.min(slides.length - 1, n));
        if (n === current) return;
        var forward = n > current;
        slides.forEach(function (s, i) {
            s.classList.toggle('is-past', i < n);
            if (i !== n) s.classList.remove('is-active');
            s.setAttribute('aria-hidden', i === n ? 'false' : 'true');
            if ('inert' in s) s.inert = i !== n;
        });
        var el = slides[n];
        // Restart entrance animations every time a slide is shown.
        void el.offsetWidth;
        el.classList.add('is-active');
        current = n;
        countUp(el);
        if (bar) bar.style.width = ((n + 1) / slides.length) * 100 + '%';
        var counter = document.querySelector('.mv-chrome .mv-count');
        if (counter) counter.textContent = String(n + 1).padStart(2, '0') + ' / ' + String(slides.length).padStart(2, '0');
        var chrome = document.querySelector('.mv-chrome');
        if (chrome) chrome.classList.toggle('on-hero', el.classList.contains('mv-slide--hero'));
        // Quiet backdrop behind dense slides (data-backdrop="soft"), full aurora elsewhere.
        if (backdrop) backdrop.classList.toggle('mv-backdrop--soft', el.dataset.backdrop === 'soft');
        if (!instant && history.replaceState) history.replaceState(null, '', '#' + (n + 1));
        void forward;
    }
    function next() { go(current + 1); }
    function prev() { go(current - 1); }

    function bindCommon() {
        document.addEventListener('keydown', function (e) {
            if (e.metaKey || e.ctrlKey || e.altKey) return;
            var k = e.key.toLowerCase();
            if (k === 't') toggleTheme();
            if (k === 'd') switchMode(body.classList.contains('mv-mode-doc') ? 'deck' : 'doc');
        });
        Array.prototype.forEach.call(document.querySelectorAll('[data-mv-action="theme"]'), function (b) { b.addEventListener('click', toggleTheme); });
    }
    function switchMode(mode) {
        var url = new URL(location.href);
        url.searchParams.set('mode', mode);
        url.hash = '';
        location.href = url.toString();
    }

    document.addEventListener('keydown', function (e) {
        if (e.metaKey || e.ctrlKey || e.altKey) return;
        switch (e.key) {
            case 'ArrowRight': case 'ArrowDown': case 'PageDown': case ' ': case 'Enter':
                e.preventDefault(); (html.dir === 'rtl' && e.key === 'ArrowRight') ? prev() : next(); break;
            case 'ArrowLeft': case 'ArrowUp': case 'PageUp': case 'Backspace':
                e.preventDefault(); (html.dir === 'rtl' && e.key === 'ArrowLeft') ? next() : prev(); break;
            case 'Home': go(0); break;
            case 'End': go(slides.length - 1); break;
            case 'f': case 'F':
                if (document.fullscreenElement) document.exitFullscreen(); else if (html.requestFullscreen) html.requestFullscreen();
                break;
        }
    });
    bindCommon();
    var tx = null;
    window.addEventListener('touchstart', function (e) { tx = e.touches[0].clientX; }, { passive: true });
    window.addEventListener('touchend', function (e) {
        if (tx === null) return;
        var dx = e.changedTouches[0].clientX - tx;
        if (Math.abs(dx) > 40) (dx < 0 ? next : prev)();
        tx = null;
    });
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-mv-action]');
        if (t) {
            var a = t.dataset.mvAction;
            if (a === 'next') next();
            if (a === 'prev') prev();
            if (a === 'doc') switchMode('doc');
            return;
        }
        if (e.target.closest('a, button, input, select, textarea')) return;
        // Click the right two-thirds to advance, the left third to go back.
        (e.clientX > window.innerWidth / 3 ? next : prev)();
    });
    window.addEventListener('hashchange', function () {
        var n = parseInt(location.hash.slice(1), 10);
        if (!isNaN(n)) go(n - 1);
    });

    // Exporter hook: window.mvGo(n) shows slide n (0-based) in its finished state.
    window.mvGo = function (n) { go(n, true); settle(slides[n]); };
    window.mvCount = slides.length;

    var start = parseInt((location.hash || '#1').slice(1), 10);
    go(isNaN(start) ? 0 : start - 1, true);
})();
