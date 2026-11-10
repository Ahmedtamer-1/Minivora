---
name: minivora-design
description: Turn any PDF, PowerPoint, Word doc, Markdown or plain notes into an animated presentation or document that looks and moves exactly like the Minivora app (aurora backdrop, frosted glass cards, deep-blue hero, AI orb, Outfit light numerals, M·INIVORA logo). Outputs a self-contained animated HTML deck (also readable as a scrolling page), plus on request a PDF, PNGs, a video, or a PowerPoint with native entrance animations. Use when the user runs /minivora-design, or asks to "make this look like Minivora", re-brand / restyle / redesign slides, a report or a pitch in the Minivora style, or turn a file into a Minivora deck.
---

# Minivora design

Re-tell the user's content in the Minivora visual language. The style, tokens and motion are taken
from the app itself (`resources/css/app.css`, `resources/css/tokens.css`, `components/logo.tsx`,
`animated-backdrop.tsx`, `ai-orb.tsx`); `references/design-system.md` documents them. You do not
hand-write CSS: you write a `deck.json`, and `scripts/build.py` renders it with the system.

`SKILL` below means this folder: `.claude/skills/minivora-design`.

## Workflow

1. **Find the input.** A path in the arguments, an attached/uploaded file, or text pasted in chat.
   No input → ask for the file (or offer to build from a topic they describe).

2. **Extract** (skip for pasted text or Markdown you can read directly):
   ```bash
   python3 SKILL/scripts/extract.py INPUT -o WORK
   ```
   Handles `.pdf .pptx .docx .md .txt .html` (and `.ppt .key .odp .doc .odt .rtf` via LibreOffice).
   Read `WORK/outline.json`. For PDFs, also look at a few `WORK/media/pages/*.png` thumbnails to see
   what the original emphasised (big numbers, charts, photos). Images found are in `WORK/media/`.
   `WORK` = a folder in the scratchpad (or `./minivora-work` if there is none).
   If a PDF reports ~0 words it is scanned/image-only: render readable pages with
   `pdftoppm -png -r 110 INPUT WORK/read` and read the text from the images yourself.

3. **Design the story, then write `WORK/deck.json`** (schema: `references/deck-schema.md`;
   full example: `examples/showcase.json`). This is the real work. Rules:
   - **Re-tell, don't transcribe.** One idea per slide. Cut filler, split dense pages, merge thin ones.
     Max ~6 bullets, ~12 words each; move long prose to `notes`.
   - **Pick the layout from the content**, never bullets by default:
     a number that matters → `big-number` or `kpis`; series over time/categories → `chart` (bar);
     shares/percentages of a whole → `chart` (progress) or `ring`; 2-6 parallel ideas → `cards`;
     steps/dates/roadmap → `timeline`; comparison → `two-column` or `table`; testimonial → `quote`;
     a strong photo/screenshot → `image` or `bullets` with `image`; chapter breaks → `section`.
   - **Rhythm**: open with `title`, close with `closing`. Alternate dense slides with an airy one
     (hero/section/quote) every 3-4 slides. Use at most ~1 hero slide in 4 so the blue stays special.
   - **Numbers**: give real numbers as numbers (`482300`, `"3.4%"`, `"$1.2M"` is fine); they count up.
     Keep the source's units and currency. Never invent data; if the source has none, don't fake a chart.
   - **Voice** (the app's): plain, calm, short, sentence case, no exclamation marks, no jargon.
     Highlight one key phrase per title at most with `==phrase==`.
   - Keep the source language. Arabic sources → `"lang": "ar"` (RTL is automatic; logo stays LTR).
   - Use extracted images by relative path from `deck.json` (they get inlined).

4. **Build:**
   ```bash
   python3 SKILL/scripts/build.py WORK/deck.json -o OUT/NAME.html
   ```
   `--mode doc` makes it a scrolling document by default (good for reports and long PDFs);
   `--theme light|dark` pins a theme (default follows the OS). Either way the reader can press
   **D** to flip deck ↔ document and **T** to flip light ↔ dark.

5. **Check it visually before handing over** (always):
   ```bash
   node SKILL/scripts/export.mjs OUT/NAME.html --png WORK/check --scale 1
   ```
   Look at every PNG. Fix overflow (text running off a card or slide), cramped or empty slides,
   orphan words in titles, a chart with one bar, by editing `deck.json` (shorter text, a different
   layout, splitting a slide) and rebuilding. Repeat until every slide is clean.

6. **Export what they asked for** (only what they asked; the HTML is always the main deliverable,
   because it carries the full motion):
   | Want | Command |
   |---|---|
   | PDF | `node SKILL/scripts/export.mjs OUT/NAME.html --pdf OUT/NAME.pdf` |
   | PowerPoint, animated | `node SKILL/scripts/export.mjs OUT/NAME.html --layers WORK/layers` then `python3 SKILL/scripts/build_pptx.py WORK/layers/manifest.json -o OUT/NAME.pptx` (add `--auto 6` for a self-running kiosk deck) |
   | Video of the animations | `node SKILL/scripts/export.mjs OUT/NAME.html --video OUT/NAME.webm --hold 4` |
   | Slide images | `node SKILL/scripts/export.mjs OUT/NAME.html --png OUT/slides` |
   Add `--theme dark` to any export for the dark look.
   The PowerPoint keeps the exact Minivora look: each slide is the background plus one picture per
   element, each with a native PowerPoint entrance (fade / rise / zoom) staggered like the web
   version, a fade transition between slides, alt text on every picture and speaker notes. Say so:
   its text is not editable in PowerPoint; edits go through `deck.json`.

7. **Deliver.** Put outputs where the user can open them (the working directory or scratchpad
   per session rules), send the HTML (and any exports) to the user, and say in a line or two what
   the deck contains and the keys: ←/→ or click to move, F fullscreen, T theme, D document view.
   If the user wants a shareable link and publishing is available, publish the HTML as an artifact.

## Outputs at a glance
- One `.html` file, fully self-contained (CSS, JS, logo, images inlined; fonts from Google Fonts with
  system fallbacks). Works offline, in any modern browser, on phones (document view reflows).
- Respects Reduce Motion, Reduce Transparency and Increase Contrast like the app does.

## When the user wants something the layouts don't cover
Prefer combining existing slide types. If you truly need a custom slide, add a renderer in
`scripts/build.py` using only classes from `assets/minivora.css` and tokens (`var(--mv-*)`), never raw
hex, and keep the entrance pattern (`data-anim` on each piece). Document new types in
`references/deck-schema.md`.

## Keeping it in sync with the app
The skill copies the app's tokens. After the design tokens change (`npm run tokens`), run
`python3 SKILL/scripts/sync_tokens.py`.
