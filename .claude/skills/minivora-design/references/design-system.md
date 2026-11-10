# Minivora design system (as extracted from the app)

Sources: `design/tokens/tokens.json` (Figma "Dashboard · HIG pass"), `resources/css/app.css`,
`resources/js/components/{logo,animated-backdrop,ai-orb}.tsx`, `components/ui/*`, `tailwind.config.js`.
The skill's `assets/tokens.css` is a copy of the app's generated token file.

## Brand
- **Logo**: the mark (`assets/logo-mark.svg`, `currentColor`) stands in for the "M", so the wordmark
  reads M·INIVORA. Outfit SemiBold, tracking 0.06em, mark height = cap height (0.7em) on the baseline,
  gap 0.08em. Always laid out LTR, even in Arabic. Colour = `--mv-brand-mark` (ink in light, white
  in dark, white on the blue hero).
- **Personality**: calm, quiet, premium, helpful. Light type weights, lots of air, one strong thing
  per screen. The backdrop is alive but never loud.

## Colour (light / dark)
| Role | Light | Dark |
|---|---|---|
| Canvas | `#EAF1F6` | `#060A12` |
| Elevated | `#FFFFFF` | `#0C1220` |
| Ink | `#0B1A26` | `#FAFAFA` |
| Ink muted | `#5B6E7E` | `#A3B0C2` |
| Accent text/link | `#156A8C` | `#7CC4E4` |
| Primary action | `#1D7AA0` | `#FAFAFA` (inverted) |
| Brand accent | `#2A8FB8` | `#2A8FB8` |
| Brand highlight (mint) | `#7FE0C8` | `#7FE0C8` |
| Hero base / glow / mint | `#135E80` / `#1D7AA0` / mint 45% | same |
| Charts 1-4 | `#2A8FB8 #1B4F72 #7FE0C8 #9FB3C3` | `#2A8FB8 #5B7FA6 #7FE0C8 #6B7F95` |
| Status success/warning/danger | `#157A52 #9A5B00 #C2362F` | `#4ADE80 #FBBF24 #F87171` |
Aurora orbs: accent blue, mint, slate in light; deep navy blues in dark. Contrast is WCAG AA.

## Type
- Outfit (Latin) + Noto Sans Arabic, JetBrains Mono for code.
- Display and numerals are **Light (300) with tight negative tracking** (-0.03 to -0.07em).
- Labels: 11px (app) uppercase, SemiBold, tracking 0.16em; plain in Arabic.
- Card titles: Regular, small. Body: Regular. Tabular figures for numbers.

## Shape and surfaces
- Radius: cards 28px, controls pill (999px), small 6-16px.
- **Card**: glass fill (white 80% / white 4% dark), 1px white stroke, soft shadow + inner top
  highlight, blur 16px saturate 140%.
- **Glass** (floating controls): blur 48px saturate 160%, thicker shadow.
- **Hero** ("Sales today"): deep blue `#135E80` with a top-left glow and bottom-right mint, two
  wandering lights, a light sweep every 6s. White text only.
- **Chips**: pill, 26px tall in app, Medium; tones = status subtle bg + status text; mint highlight.
- **AI orb**: glassy sphere (mint → blue → navy) with a spinning conic aurora inside; breathes
  idle, ripples rings when thinking/speaking. **Teal orb** and **sphere** are decorative accents.

## Motion
- Ease: `cubic-bezier(0.22, 1, 0.36, 1)` for everything that enters; spring
  `cubic-bezier(0.34, 1.56, 0.64, 1)` for pops.
- Entrances: rise (8px up + fade, 0.35s in app; 24px/0.7s on the larger stage), staggered 25-120ms.
- Numbers count up with cubic ease-out (~1.1-1.4s). Bars and rings grow from zero.
- Backdrop: 5 vmax-sized orbs drifting 26-38s, a still conic sheen, film grain at 5%.
- Hover: lift 3-4px with a thicker shadow.
- Reduce Motion stops everything; Reduce Transparency makes surfaces opaque; Increase Contrast
  strengthens borders and muted text.

## Do / don't
- Do: one hero number per screen, blue hero sparingly, white space, plain words, sentence case.
- Don't: raw hex in components (use `var(--mv-*)`), heavy weights for big type, more than one
  accent highlight per title, emoji as icons, busy backgrounds behind text.
