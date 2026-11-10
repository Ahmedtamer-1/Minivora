#!/usr/bin/env python3
"""Build a Minivora-styled, animated, self-contained HTML deck (or scrolling document) from deck.json.

    python3 build.py deck.json -o out/deck.html [--mode deck|doc] [--theme light|dark|auto]

The JSON schema is documented in references/deck-schema.md. Images referenced by relative path are
inlined as data URIs, and the CSS, JS and logo are inlined too, so the output is one file you can
email, open offline, or publish.
"""
from __future__ import annotations

import argparse
import base64
import html
import json
import mimetypes
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ASSETS = HERE.parent / "assets"

# ----------------------------------------------------------------------------- helpers

def esc(s) -> str:
    return html.escape(str(s if s is not None else ""), quote=True)


def md(s) -> str:
    """Tiny inline markdown: **bold**, *em*, `code`, [text](url), and ==highlight== (accent colour)."""
    t = esc(s)
    t = re.sub(r"`([^`]+)`", r'<code class="mv-inline-code">\1</code>', t)
    t = re.sub(r"\*\*([^*]+)\*\*", r"<strong>\1</strong>", t)
    t = re.sub(r"(?<![\w*])\*([^*]+)\*(?!\w)", r"<em>\1</em>", t)
    t = re.sub(r"==([^=]+)==", r'<span class="mv-accent">\1</span>', t)
    t = re.sub(r"\[([^\]]+)\]\((https?://[^)\s]+)\)", r'<a href="\2">\1</a>', t)
    return t


def paras(text) -> str:
    if not text:
        return ""
    if isinstance(text, list):
        parts = text
    else:
        parts = [p for p in re.split(r"\n\s*\n", str(text)) if p.strip()]
    return "".join(f'<p class="mv-body" data-anim>{md(p)}</p>' for p in parts)


BASE_DIR = Path.cwd()


def image_src(path: str) -> str:
    if not path:
        return ""
    if path.startswith(("http://", "https://", "data:")):
        return path
    p = (BASE_DIR / path).resolve()
    if not p.exists():
        print(f"warning: image not found: {p}", file=sys.stderr)
        return ""
    mime = mimetypes.guess_type(p.name)[0] or "image/png"
    return f"data:{mime};base64," + base64.b64encode(p.read_bytes()).decode()


def logo_svg() -> str:
    svg = (ASSETS / "logo-mark.svg").read_text()
    return svg.replace("<svg ", '<svg aria-hidden="true" ', 1)


def logo(cls: str = "", name: bool = True) -> str:
    inner = logo_svg() + ('<span aria-hidden="true">INIVORA</span>' if name else "")
    return f'<span class="mv-logo {cls}" role="img" aria-label="Minivora">{inner}</span>'


def ai_orb(size: int, state: str = "idle", cls: str = "") -> str:
    return (
        f'<span class="mv-ai-orb {cls}" data-state="{esc(state)}" aria-hidden="true" '
        f'style="width:{size}px;height:{size}px"><span class="mv-ai-rings"><span></span><span></span><span></span></span></span>'
    )


# Line icons in the app's style (lucide, stroke 2, ISC licence).
ICONS = {
    "sparkles": '<path d="M9.94 14.06 4 20M12 3l1.9 5.8a2 2 0 0 0 1.3 1.3L21 12l-5.8 1.9a2 2 0 0 0-1.3 1.3L12 21l-1.9-5.8a2 2 0 0 0-1.3-1.3L3 12l5.8-1.9a2 2 0 0 0 1.3-1.3z"/>',
    "chart": '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>',
    "bars": '<path d="M12 20V10M18 20V4M6 20v-4"/>',
    "package": '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>',
    "users": '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    "cart": '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
    "shield": '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
    "zap": '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
    "target": '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
    "globe": '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20M2 12h20"/>',
    "check": '<path d="M20 6 9 17l-5-5"/>',
    "clock": '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    "wallet": '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
    "message": '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
    "layers": '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65M22 12.65l-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
    "store": '<path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4M2 7h20"/>',
    "truck": '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2M15 18H9M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
    "lightbulb": '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5M9 18h6M10 22h4"/>',
    "rocket": '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09zM12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
    "heart": '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
    "alert": '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>',
    "file": '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4M16 13H8M16 17H8M10 9H8"/>',
    "arrow-right": '<path d="M5 12h14M12 5l7 7-7 7"/>',
    "arrow-left": '<path d="M19 12H5M12 19l-7-7 7-7"/>',
    "sun-moon": '<path d="M12 8a2.83 2.83 0 0 0 4 4 4 4 0 1 1-4-4M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4"/>',
    "scroll": '<path d="M8 21h12a2 2 0 0 0 2-2v-2H10v2a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v3h4M19 17V5a2 2 0 0 0-2-2H4"/>',
    "presentation": '<path d="M2 3h20M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3M7 21l5-5 5 5"/>',
}


def icon(name: str) -> str:
    body = ICONS.get(name or "", ICONS["sparkles"])
    return (
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        f'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{body}</svg>'
    )


def number_span(v, cls="", prefix="", suffix="", decimals=None) -> str:
    """Count-up span for numeric values; plain text for anything else (e.g. "Q3" or "2.4M")."""
    raw = v
    if isinstance(v, str):
        m = re.fullmatch(r"\s*([^\d\-+.]*)([-+]?[\d,]*\.?\d+)\s*([^\d]*)\s*", v)
        if m and m.group(2).replace(",", "").replace(".", "").lstrip("+-").isdigit():
            prefix = prefix or m.group(1)
            suffix = suffix or m.group(3)
            raw = m.group(2).replace(",", "")
        else:
            return f'<span class="{cls}">{esc(v)}</span>'
    try:
        num = float(raw)
    except (TypeError, ValueError):
        return f'<span class="{cls}">{esc(v)}</span>'
    if decimals is None:
        if isinstance(raw, (int, float)):
            decimals = 0 if float(raw).is_integer() else min(len(repr(float(raw)).split(".")[1]), 2)
        else:
            decimals = len(str(raw).split(".")[1]) if "." in str(raw) else 0
    shown = f"{num:,.{decimals}f}"
    return (
        f'<span class="tabular {cls}" data-count="{num}" data-decimals="{decimals}" '
        f'data-prefix="{esc(prefix)}" data-suffix="{esc(suffix)}">{esc(prefix)}{shown}{esc(suffix)}</span>'
    )


def delta(d) -> str:
    if d is None or d == "":
        return ""
    s = str(d).strip()
    kind = "flat"
    try:
        n = float(re.sub(r"[^\d.\-+]", "", s) or 0)
        kind = "up" if n > 0 else "down" if n < 0 else "flat"
        if n > 0 and not s.startswith("+"):
            s = "+" + s
    except ValueError:
        pass
    arrow = {"up": "↑", "down": "↓", "flat": "→"}[kind]
    return f'<span class="mv-delta mv-delta--{kind}">{arrow} {esc(s.lstrip("+-") if kind != "flat" else s)}</span>'


def head(s: dict, extra: str = "") -> str:
    k = f'<span class="mv-label" data-anim>{esc(s["kicker"])}</span>' if s.get("kicker") else ""
    t = f'<h2 class="mv-h2 mv-words">{md(s["title"])}</h2>' if s.get("title") else ""
    sub = f'<p class="mv-lead" style="margin-top:14px;max-width:1100px" data-anim>{md(s["subtitle"])}</p>' if s.get("subtitle") else ""
    if not (k or t or sub or extra):
        return ""
    return f'<header class="mv-slide-head"><div>{k}{t}{sub}</div>{extra}</header>'


def chip(text, tone="glass") -> str:
    return f'<span class="mv-chip mv-chip--{esc(tone)}">{md(text)}</span>'


# ----------------------------------------------------------------------------- slide renderers

def r_title(s, deck):
    meta = " · ".join(esc(x) for x in [s.get("author") or deck.get("author"), s.get("date") or deck.get("date")] if x)
    tags = "".join(chip(t, "white") for t in s.get("tags", []))
    return "hero", f"""
<div class="mv-hero mv-shine"><span class="mv-shine-sweep"></span>
  <div style="display:flex;justify-content:space-between;align-items:center" data-anim="fade">
    {logo("mv-logo--onhero", True).replace('class="mv-logo', 'style="font-size:40px" class="mv-logo')}
    {f'<span class="mv-chip mv-chip--white">{esc(s["badge"])}</span>' if s.get("badge") else ""}
  </div>
  <div style="margin-top:auto;max-width:1150px">
    {f'<p class="mv-label" data-anim>{esc(s["kicker"])}</p>' if s.get("kicker") else ""}
    <h1 class="mv-display mv-words" style="margin-top:22px">{md(s.get("title", deck.get("title", "")))}</h1>
    {f'<p class="mv-lead" style="margin-top:28px;max-width:980px" data-anim>{md(s["subtitle"])}</p>' if s.get("subtitle") else ""}
    <div style="display:flex;gap:12px;align-items:center;margin-top:40px;flex-wrap:wrap" data-anim>{tags}{f'<span class="mv-caption">{meta}</span>' if meta else ""}</div>
  </div>
  <div class="mv-float mv-deco" style="position:absolute;right:110px;top:170px" data-anim="scale">{ai_orb(220, s.get("orb", "idle"))}</div>
</div>"""


def r_section(s, deck):
    num = s.get("number")
    return "", f"""
<div class="mv-slide-body" style="justify-content:center">
  <div class="mv-section-row" style="display:flex;align-items:flex-end;gap:56px">
    {f'<span class="mv-numeral mv-gradient-text" style="font-size:240px" data-anim="scale">{esc(str(num).zfill(2))}</span>' if num is not None else ""}
    <div style="padding-bottom:30px;max-width:1000px">
      {f'<p class="mv-label" data-anim>{esc(s["kicker"])}</p>' if s.get("kicker") else ""}
      <h2 class="mv-h1 mv-words" style="margin-top:18px">{md(s.get("title", ""))}</h2>
      {f'<p class="mv-lead" style="margin-top:22px" data-anim>{md(s["subtitle"])}</p>' if s.get("subtitle") else ""}
    </div>
  </div>
  <div class="mv-sphere mv-float mv-deco" style="position:absolute;width:420px;height:420px;right:-90px;top:-110px;opacity:.9" aria-hidden="true"></div>
  <div class="mv-orb-teal mv-float mv-deco" style="position:absolute;width:90px;height:90px;right:250px;bottom:150px;animation-delay:-2s" aria-hidden="true"></div>
</div>"""


def _bullet_items(items):
    out = []
    for b in items:
        if isinstance(b, dict):
            t = f'<strong>{md(b.get("title", ""))}</strong>' if b.get("title") else ""
            txt = md(b.get("text", ""))
            sep = " — " if t and txt else ""
            out.append(f"<li data-anim><span>{t}{sep}<span class=\"mv-muted\">{txt}</span></span></li>")
        else:
            out.append(f"<li data-anim><span>{md(b)}</span></li>")
    return "".join(out)


def r_bullets(s, deck):
    cls = "mv-list mv-list--num" if s.get("numbered") else "mv-list"
    if len(s.get("bullets", [])) <= 4:
        cls += " mv-list--lg"
    lst = f'<ul class="{cls}">{_bullet_items(s.get("bullets", []))}</ul>'
    intro = f'<p class="mv-lead" data-anim style="margin-bottom:28px">{md(s["intro"])}</p>' if s.get("intro") else ""
    if s.get("image"):
        fit = " mv-media--contain" if s.get("fit") == "contain" else ""
        return "soft", head(s) + f"""
<div class="mv-split">
  <div>{intro}{lst}</div>
  <figure class="mv-media{fit}" style="margin:0;height:100%;max-height:640px" data-anim="blur"><img src="{image_src(s["image"])}" alt="{esc(s.get("alt", ""))}"></figure>
</div>"""
    aside = ""
    if s.get("callout"):
        c = s["callout"]
        aside = f"""<aside class="mv-hero" style="width:440px;flex-shrink:0;display:flex;flex-direction:column" data-anim="pop">
  <span class="mv-label">{esc(c.get("label", "Key point"))}</span>
  {f'<p class="mv-numeral-sm" style="margin-top:auto">{number_span(c["value"])}</p>' if c.get("value") is not None else ""}
  <p class="mv-body" style="margin-top:{'14px' if c.get('value') is not None else 'auto'}">{md(c.get("text", ""))}</p>
</aside>"""
    return "soft", head(s) + f"""
<div class="mv-slide-body" style="flex-direction:row;gap:48px">
  <div class="mv-card" style="flex:1;padding:48px 56px;display:flex;flex-direction:column;justify-content:center" data-anim>{intro}{lst}</div>{aside}
</div>"""


def r_kpis(s, deck):
    items = s.get("kpis", [])
    n = len(items)
    cols = 4 if n >= 4 else max(n, 1)
    cards = []
    for i, k in enumerate(items):
        hero = k.get("hero") or (i == 0 and s.get("highlight_first", True))
        cls = "mv-hero mv-shine" if hero else "mv-card mv-lift"
        sweep = '<span class="mv-shine-sweep"></span>' if hero else ""
        d = delta(k.get("delta"))
        if hero and d:
            d = f'<span class="mv-chip mv-chip--white" style="height:32px;font-size:16px">{esc(k.get("delta"))}</span>'
        cards.append(f"""<div class="{cls} mv-kpi" style="display:flex;flex-direction:column;min-height:300px" data-anim="pop">{sweep}
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px"><span class="mv-card-title">{md(k.get("label", ""))}</span>{d}</div>
  <p class="mv-numeral-sm" style="margin-top:auto;font-size:68px">{number_span(k.get("value"), prefix=k.get("prefix", ""), suffix=k.get("suffix", ""), decimals=k.get("decimals"))}</p>
  {f'<p class="mv-caption" style="margin-top:10px">{md(k["note"])}</p>' if k.get("note") else ""}
</div>""")
    return "soft", head(s) + f'<div class="mv-slide-body" style="justify-content:center"><div class="mv-grid mv-grid-{cols}">{"".join(cards)}</div>{_footnote(s)}</div>'


def _footnote(s):
    return f'<p class="mv-caption" style="margin-top:28px" data-anim>{md(s["footnote"])}</p>' if s.get("footnote") else ""


def r_big_number(s, deck):
    return "hero", f"""
<div class="mv-hero mv-shine" style="justify-content:center"><span class="mv-shine-sweep"></span>
  <p class="mv-label" data-anim>{esc(s.get("label", ""))}</p>
  <p class="mv-numeral" style="font-size:260px;margin-top:20px" data-anim="scale">{number_span(s.get("value"), prefix=s.get("prefix", ""), suffix=s.get("suffix", ""), decimals=s.get("decimals"))}</p>
  <div style="display:flex;gap:16px;align-items:center;margin-top:28px" data-anim>
    {f'<span class="mv-chip mv-chip--white">{esc(s["delta"])}</span>' if s.get("delta") else ""}
    <p class="mv-lead" style="max-width:1000px">{md(s.get("caption", ""))}</p>
  </div>
</div>"""


def r_cards(s, deck):
    items = s.get("cards", [])
    n = len(items)
    cols = s.get("columns") or (2 if n in (2, 4) else 3 if n in (3, 5, 6, 9) else 4)
    cards = []
    for c in items:
        tag = chip(c["tag"], c.get("tone", "info")) if c.get("tag") else ""
        ic = f'<span class="mv-icon">{icon(c.get("icon"))}</span>' if c.get("icon", "auto") else ""
        cards.append(f"""<article class="mv-card mv-lift" style="display:flex;flex-direction:column;gap:18px" data-anim="pop">
  <div style="display:flex;justify-content:space-between;align-items:flex-start">{ic}{tag}</div>
  <h3 class="mv-h3">{md(c.get("title", ""))}</h3>
  <p class="mv-body mv-muted" style="font-size:20px">{md(c.get("text", ""))}</p>
</article>""")
    return "soft", head(s) + f'<div class="mv-slide-body" style="justify-content:center"><div class="mv-grid mv-grid-{cols}">{"".join(cards)}</div>{_footnote(s)}</div>'


def r_chart(s, deck):
    data = s.get("data", [])
    unit = s.get("unit", "")
    prefix = s.get("prefix", "")
    vals = [float(d.get("value", 0) or 0) for d in data] or [0]
    mx = max(vals) or 1
    kind = s.get("kind", "bar")
    if kind == "progress":
        rows = []
        for i, d in enumerate(data):
            v = float(d.get("value", 0) or 0)
            pct = v / (float(s.get("max") or mx)) * 100
            rows.append(f"""<div data-anim>
  <div style="display:flex;justify-content:space-between;margin-bottom:12px"><span class="mv-body">{md(d.get("label", ""))}</span><span class="mv-body" style="font-weight:500">{number_span(v, prefix=prefix, suffix=unit)}</span></div>
  <div class="mv-track"><span style="--w:{pct:.1f}%;--i:{i}"></span></div>
</div>""")
        chart = f'<div style="display:grid;gap:30px;align-content:center;height:100%">{"".join(rows)}</div>'
    else:
        peak = vals.index(max(vals))
        bars = []
        for i, d in enumerate(data):
            v = float(d.get("value", 0) or 0)
            h = max(v / mx * 100, 1.5)
            bars.append(f"""<div class="mv-bar{' is-peak' if i == peak else ''}">
  <span class="mv-bar-val">{number_span(v, prefix=prefix, suffix=unit)}</span>
  <div class="mv-bar-col" style="--h:{h:.1f}%;flex:0 0 auto;height:calc(var(--h) * 0.78)"></div>
  <span class="mv-bar-lbl">{esc(d.get("label", ""))}</span>
</div>""")
        chart = f'<div class="mv-bars">{"".join(bars)}</div>'
    insight = ""
    if s.get("insight"):
        ins = s["insight"]
        insight = f"""<aside class="mv-card" style="width:420px;flex-shrink:0;display:flex;flex-direction:column;gap:18px" data-anim="pop">
  <div style="display:flex;align-items:center;gap:14px">{ai_orb(44, "speaking")}<span class="mv-card-title" style="margin:0">{esc(ins.get("label", "What this means"))}</span></div>
  <p class="mv-body">{md(ins.get("text", ""))}</p>
</aside>"""
    return "soft", head(s) + f"""
<div class="mv-slide-body" style="flex-direction:row;gap:32px">
  <div class="mv-card" style="flex:1;display:flex;flex-direction:column;padding:40px 44px" data-anim>
    {f'<span class="mv-card-title">{md(s["chart_title"])}</span>' if s.get("chart_title") else ""}
    <div class="mv-fill" style="margin-top:20px">{chart}</div>
  </div>{insight}
</div>"""


def r_ring(s, deck):
    v = float(s.get("value", 0) or 0)
    size, stroke = 380, 26
    r = (size - stroke) / 2
    items = s.get("items") or s.get("bullets") or []
    return "soft", head(s) + f"""
<div class="mv-split">
  <div class="mv-ring" data-value="{v}" data-anim="scale" style="justify-self:center">
    <svg width="{size}" height="{size}" viewBox="0 0 {size} {size}" aria-hidden="true">
      <defs><linearGradient id="mvRingGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="var(--mv-brand-highlight)"/><stop offset="1" stop-color="var(--mv-brand-accent)"/></linearGradient></defs>
      <circle cx="{size/2}" cy="{size/2}" r="{r}" fill="none" stroke="var(--mv-chart-track)" stroke-width="{stroke}"/>
      <circle class="mv-ring-fg" cx="{size/2}" cy="{size/2}" r="{r}" fill="none" stroke="url(#mvRingGrad)" stroke-width="{stroke}"/>
    </svg>
    <div class="mv-ring-label"><p class="mv-numeral" style="font-size:110px">{number_span(v, suffix=s.get("suffix", "%"), decimals=s.get("decimals"))}</p><p class="mv-caption" style="margin-top:8px">{md(s.get("label", ""))}</p></div>
  </div>
  <div>{paras(s.get("text"))}{f'<ul class="mv-list" style="margin-top:24px">{_bullet_items(items)}</ul>' if items else ""}</div>
</div>"""


def r_quote(s, deck):
    who = " · ".join(esc(x) for x in [s.get("author"), s.get("role")] if x)
    return "", f"""
<div class="mv-slide-body mv-center" style="justify-content:center">
  <div style="max-width:1240px;text-align:start">
    <blockquote class="mv-quote mv-words" style="margin:0">{md(s.get("quote", ""))}</blockquote>
    {f'<div style="display:flex;align-items:center;gap:16px;margin-top:44px" data-anim><span class="mv-orb-teal" style="width:44px;height:44px"></span><span class="mv-lead">{who}</span></div>' if who else ""}
  </div>
</div>"""


def r_table(s, deck):
    cols = s.get("columns", [])
    numeric = set(s.get("numeric_columns", []))
    if not numeric and s.get("rows"):
        for j in range(len(cols)):
            if all(re.fullmatch(r"[-+]?[^\w\s]?[\d,]+(\.\d+)?%?", str(r[j]).strip() or "x") for r in s["rows"] if j < len(r)):
                numeric.add(j)
    th = "".join(f'<th class="{"num" if j in numeric else ""}">{esc(c)}</th>' for j, c in enumerate(cols))
    trs = []
    for row in s.get("rows", []):
        tds = "".join(f'<td class="{"num tabular" if j in numeric else ""}">{md(c)}</td>' for j, c in enumerate(row))
        trs.append(f"<tr data-anim>{tds}</tr>")
    return "soft", head(s) + f"""
<div class="mv-slide-body"><div class="mv-card mv-table-wrap" style="padding:36px 32px 20px;overflow:auto" data-anim>
  <table class="mv-table"><thead><tr>{th}</tr></thead><tbody>{"".join(trs)}</tbody></table>
</div>{_footnote(s)}</div>"""


def r_timeline(s, deck):
    steps = []
    for i, st in enumerate(s.get("steps", [])):
        state = st.get("state", "")
        cls = {"done": "is-done", "now": "is-now"}.get(state, "")
        mark = "✓" if state == "done" else str(i + 1)
        when = f'<span class="mv-label" style="display:block;margin-top:22px">{esc(st["when"])}</span>' if st.get("when") else ""
        steps.append(f"""<div class="mv-step {cls}" data-anim="pop"><span class="mv-step-dot">{mark}</span>{when}<h3 style="{'margin-top:10px' if when else ''}">{md(st.get("title", ""))}</h3><p>{md(st.get("text", ""))}</p></div>""")
    return "", head(s) + f'<div class="mv-slide-body" style="justify-content:center"><div class="mv-timeline">{"".join(steps)}</div></div>'


def r_image(s, deck):
    fit = " mv-media--contain" if s.get("fit") == "contain" else ""
    cap = f'<p class="mv-caption" style="margin-top:18px" data-anim>{md(s["caption"])}</p>' if s.get("caption") else ""
    return "soft", head(s) + f"""
<div class="mv-slide-body"><figure class="mv-media{fit} mv-fill" style="margin:0" data-anim="blur"><img src="{image_src(s.get("image", ""))}" alt="{esc(s.get("alt", ""))}"></figure>{cap}</div>"""


def _col(c):
    if not c:
        return ""
    b = c.get("bullets") or []
    return f"""<div class="mv-card" data-anim="pop">
  {f'<span class="mv-label">{esc(c["kicker"])}</span>' if c.get("kicker") else ""}
  {f'<h3 class="mv-h3" style="margin-top:14px">{md(c["title"])}</h3>' if c.get("title") else ""}
  <div class="mv-stack" style="margin-top:18px">{paras(c.get("text"))}</div>
  {f'<ul class="mv-list" style="margin-top:22px">{_bullet_items(b)}</ul>' if b else ""}
</div>"""


def r_two_column(s, deck):
    return "soft", head(s) + f'<div class="mv-slide-body"><div class="mv-grid mv-grid-2" style="align-items:stretch">{_col(s.get("left"))}{_col(s.get("right"))}</div></div>'


def r_text(s, deck):
    return "soft", head(s) + f'<div class="mv-slide-body"><div class="mv-card mv-stack" style="padding:48px;max-width:1240px" data-anim>{paras(s.get("body") or s.get("text"))}</div></div>'


def r_closing(s, deck):
    contact = "".join(chip(c, "white") for c in ([s["contact"]] if isinstance(s.get("contact"), str) else s.get("contact", [])))
    return "hero", f"""
<div class="mv-hero mv-shine" style="align-items:center;justify-content:center;text-align:center"><span class="mv-shine-sweep"></span>
  <div data-anim="blur">{ai_orb(170, "speaking")}</div>
  <h2 class="mv-display mv-words" style="margin-top:48px;max-width:1200px">{md(s.get("title", "Thank you"))}</h2>
  {f'<p class="mv-lead" style="margin-top:24px;max-width:900px" data-anim>{md(s["subtitle"])}</p>' if s.get("subtitle") else ""}
  <div style="display:flex;gap:12px;margin-top:40px;flex-wrap:wrap;justify-content:center" data-anim>{contact}</div>
  <div class="mv-closing-logo" style="position:absolute;bottom:56px;left:0;right:0;display:flex;justify-content:center" data-anim="fade">{logo("mv-logo--onhero").replace('class="mv-logo', 'style="font-size:34px" class="mv-logo')}</div>
</div>"""


RENDER = {
    "title": r_title, "section": r_section, "bullets": r_bullets, "kpis": r_kpis, "big-number": r_big_number,
    "cards": r_cards, "chart": r_chart, "ring": r_ring, "quote": r_quote, "table": r_table,
    "timeline": r_timeline, "image": r_image, "two-column": r_two_column, "text": r_text, "closing": r_closing,
}


# ----------------------------------------------------------------------------- page

def build(deck: dict, mode: str, theme: str) -> str:
    css = (ASSETS / "tokens.css").read_text() + "\n" + (ASSETS / "minivora.css").read_text()
    css += "\n.mv-inline-code{font-family:var(--mv-font-mono);font-size:.88em;padding:.1em .4em;border-radius:8px;background:var(--mv-action-secondary)}\n"
    js = (ASSETS / "minivora.js").read_text()
    lang = deck.get("lang", "en")
    rtl = lang.startswith("ar") or deck.get("dir") == "rtl"
    slides_html = []
    for i, s in enumerate(deck.get("slides", [])):
        typ = s.get("type", "bullets")
        fn = RENDER.get(typ)
        if not fn:
            print(f"warning: unknown slide type {typ!r}, rendering as text", file=sys.stderr)
            fn = r_text
        kind, inner = fn(s, deck)
        cls = "mv-slide mv-slide--hero" if kind == "hero" else "mv-slide"
        bd = ' data-backdrop="soft"' if kind == "soft" else ""
        notes = f'<aside class="mv-notes" hidden>{esc(s["notes"])}</aside>' if s.get("notes") else ""
        label = esc(re.sub(r"[*=`]", "", str(s.get("title") or s.get("label") or typ)))
        slides_html.append(f'<section class="{cls}"{bd} aria-roledescription="slide" aria-label="{i + 1}: {label}">{inner}{notes}</section>')

    theme_attr = f' data-theme="{theme}"' if theme in ("light", "dark") else ""
    title = esc(re.sub(r"[*=`]", "", deck.get("title", "Minivora")))
    favicon = "data:image/svg+xml;base64," + base64.b64encode(
        (ASSETS / "logo-mark.svg").read_text().replace('fill="currentColor"', 'fill="#1D7AA0"').encode()
    ).decode()
    return f"""<!doctype html>
<html lang="{esc(lang)}" dir="{'rtl' if rtl else 'ltr'}"{theme_attr}>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{title}</title>
<meta name="description" content="{esc(deck.get("description", "A Minivora presentation"))}">
<link rel="icon" href="{favicon}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Noto+Sans+Arabic:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
{css}
</style>
</head>
<body class="mv-mode-{mode}">
<div class="mv-backdrop" aria-hidden="true">
  <div class="mv-sheen"></div>
  <div class="mv-orb mv-orb--2"></div><div class="mv-orb mv-orb--3"></div>
  <div class="mv-orb mv-orb--1"></div><div class="mv-orb mv-orb--4"></div><div class="mv-orb mv-orb--5"></div>
  <div class="mv-grain"></div>
</div>
<div class="mv-progress" aria-hidden="true"><span></span></div>
<nav class="mv-doc-top mv-glass" aria-label="Document">
  {logo()}
  <span style="display:flex;gap:2px">
    <button type="button" data-mv-action="deck" aria-label="Present as slides">{icon("presentation")}</button>
    <button type="button" data-mv-action="theme" aria-label="Switch light or dark">{icon("sun-moon")}</button>
  </span>
</nav>
<main class="mv-viewport">
  <div class="mv-stage">
    {"".join(slides_html)}
    <div class="mv-chrome" aria-hidden="true">{logo()}<span class="mv-count"></span></div>
  </div>
</main>
<nav class="mv-nav mv-glass" aria-label="Slides">
  <button type="button" data-mv-action="prev" aria-label="Previous slide">{icon("arrow-right" if rtl else "arrow-left")}</button>
  <button type="button" data-mv-action="theme" aria-label="Switch light or dark">{icon("sun-moon")}</button>
  <button type="button" data-mv-action="doc" aria-label="Read as a scrolling document">{icon("scroll")}</button>
  <button type="button" data-mv-action="next" aria-label="Next slide">{icon("arrow-left" if rtl else "arrow-right")}</button>
</nav>
<script>
{js}
</script>
</body>
</html>
"""


def main():
    global BASE_DIR
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("deck", help="deck.json")
    ap.add_argument("-o", "--out", help="output .html (default: next to deck.json)")
    ap.add_argument("--mode", choices=["deck", "doc"], help="slides (default) or scrolling document")
    ap.add_argument("--theme", choices=["auto", "light", "dark"], help="default: deck.json 'theme' or auto (follows OS)")
    a = ap.parse_args()
    src = Path(a.deck).resolve()
    BASE_DIR = src.parent
    deck = json.loads(src.read_text())
    mode = a.mode or deck.get("mode", "deck")
    theme = a.theme or deck.get("theme", "auto")
    out = Path(a.out) if a.out else src.with_suffix(".html")
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(build(deck, mode, theme))
    print(f"wrote {out} ({len(deck.get('slides', []))} slides, mode={mode}, theme={theme}, {out.stat().st_size // 1024} KB)")


if __name__ == "__main__":
    main()
