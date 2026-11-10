# deck.json schema

```jsonc
{
  "title": "Deck title (browser tab, title slide fallback)",
  "description": "One sentence",
  "author": "Shown on the title slide",
  "date": "Shown on the title slide",
  "lang": "en",            // "ar" → right-to-left automatically
  "theme": "auto",         // auto | light | dark
  "mode": "deck",          // deck (slides) | doc (scrolling document)
  "slides": [ { "type": "...", ... , "notes": "speaker notes (optional, any slide)" } ]
}
```

Text fields accept light inline markdown: `**bold**`, `*italic*`, `` `code` ``, `[link](https://…)`,
and `==accent==` (brand colour; mint on blue hero slides). Most slides also accept `kicker` (the small
uppercase label above the title), `title`, `subtitle`, and `footnote`.

Numbers: a number (`3214`) or numeric string (`"3.4%"`, `"$1.2"`, `"482,300"`) counts up when the
slide appears. Non-numeric strings (`"Q3"`, `"2.4M users"`) are shown as is. `prefix`, `suffix`,
`decimals` override the formatting.

## Slide types

| type | Use for | Fields |
|---|---|---|
| `title` | Opening, full-bleed blue hero with logo and AI orb | `kicker`, `title`, `subtitle`, `tags` [str], `badge`, `author`, `date`, `orb` (idle\|thinking\|speaking) |
| `section` | Chapter break, giant gradient number | `number`, `kicker`, `title`, `subtitle` |
| `bullets` | A short list | `bullets` [str or {title, text}], `numbered`, `intro`, `callout` {label, value, text} (blue side card), or `image` + `alt` + `fit` (cover\|contain) for a split layout |
| `kpis` | 2-4 headline metrics | `kpis` [{label, value, prefix, suffix, decimals, delta ("+12%"), note, hero}], `highlight_first` (default true: first card is the blue hero) |
| `big-number` | One number that is the point | `label`, `value`, `prefix`, `suffix`, `decimals`, `delta`, `caption` |
| `cards` | 2-6 parallel ideas / features | `cards` [{icon, title, text, tag, tone}], `columns` |
| `chart` | Bars over time/categories, or progress bars for shares | `kind` (bar\|progress), `data` [{label, value}], `unit`, `prefix`, `max` (progress), `chart_title`, `insight` {label, text} (AI-orb side card) |
| `ring` | One percentage of a whole | `value` (0-100), `label`, `suffix`, `text`, `items` [str] |
| `quote` | Testimonial or key statement | `quote`, `author`, `role` |
| `table` | Structured comparison (≤ 7 rows, ≤ 5 columns) | `columns` [str], `rows` [[str]], `numeric_columns` [index] (auto-detected) |
| `timeline` | Steps, roadmap, history (3-5 steps) | `steps` [{when, title, text, state (done\|now)}] |
| `image` | A photo/screenshot that carries the slide | `image` (path relative to deck.json, or URL), `alt`, `caption`, `fit` |
| `two-column` | Before/after, pros/cons | `left`, `right`: {kicker, title, text, bullets} |
| `text` | A paragraph or two that can't be cut | `body` (string, blank line = new paragraph, or [str]) |
| `closing` | Thank-you / call to action, blue hero with speaking orb | `title`, `subtitle`, `contact` (str or [str]) |

Icons for `cards`: sparkles, chart, bars, package, users, cart, shield, zap, target, globe, check,
clock, wallet, message, layers, store, truck, lightbulb, rocket, heart, alert, file.

Chip tones (`cards[].tone`): highlight (mint), info, success, warning, danger, neutral, glass.

## Fit limits (1600×900 stage)
- Titles: ≤ ~60 characters (two lines at most).
- `bullets`: ≤ 6 items; ≤ 4 gets the large size.
- `kpis`: ≤ 4. `cards`: ≤ 6. `chart` bars: ≤ 12. `timeline`: ≤ 5. `table`: ≤ 7 rows.
- Always check the rendered PNGs; split a slide rather than squeeze it.
