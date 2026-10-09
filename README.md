# Minivora

**A multi-store e-commerce platform for small Egyptian sellers, with an AI management team and a real accounting ledger.**
Sellers create a store in one step and get `theirstore.<domain>` instantly, Shopify-style: one app, one database, many stores.

| Document | What it covers |
|---|---|
| [`docs/SRS.docx`](docs/SRS.docx) | Every requirement, with IDs like `FR-CAT-01` and `NFR-SEC-05` |
| `docs/Technical-Overview` | Short version of the stack, hosting stages and team setup |
| This README | How the code is organised, how to run it, how to deploy it |

Reference requirement IDs in branches, commits, PRs and tests so every result traces back to the SRS at the defense.

---

## Contents

1. [The stack](#1-the-stack)
2. [Hosting stages](#2-hosting-stages)
3. [Repository structure](#3-repository-structure)
4. [How the pieces connect](#4-how-the-pieces-connect)
5. [Who works where](#5-who-works-where)
6. [Run it on your computer](#6-run-it-on-your-computer)
7. [Deploy to Hostinger](#7-deploy-to-hostinger)
8. [Team rules](#8-team-rules)
9. [Testing](#9-testing)
10. [Build order](#10-build-order)

---

## 1. The stack

Everything runs on plain PHP + MySQL hosting, so deploying is as easy as Adorna EG's: no Docker, no root access, no Node or Python on the server.

| Layer | Technology | Why |
|---|---|---|
| Backend | Laravel (PHP 8.3+) | Runs on any Hostinger or cPanel plan; Adorna's PHP logic ports over directly |
| Database | MySQL 8 or MariaDB | Included in every hosting plan |
| Storefront | Blade templates (server-rendered HTML) | Fast pages, full SEO, works without JavaScript |
| Dashboard | React + TypeScript through Inertia.js | Typed, modern UI with no separate API server to host |
| Typed data | `spatie/laravel-data` + TypeScript types generated from it | One definition validates input in PHP and types it in React |
| Background work | Laravel scheduler + database queue, run by one cron line | Shared hosting does not allow always-running processes |
| AI | Gemini, Groq and OpenRouter free APIs over HTTPS | Free; keys rotate when one hits its limit |
| Styling | Design tokens exported from Figma (SCRUM-102) | One source for colors, spacing, radius, glass styles |
| Checks | Pest, PHPStan, Pint, ESLint, `tsc`, Playwright | Run on every push in GitHub Actions |

Five rules shape the codebase:

1. **Multi-store from day one.** Every table except `stores` and `users` has a `store_id`, and queries are filtered by it automatically (NFR-SEC-05).
2. **Money is integer piasters.** 1 EGP = 100 piasters in MySQL, PHP and TypeScript. Never floats.
3. **AI never touches the database.** Agents call the same permission-checked services the dashboard uses (FR-AI-06). Code computes every number; the AI only explains it (FR-AI-03).
4. **Every money event posts to the ledger** as a balanced journal entry (FR-LED-02, FR-LED-03).
5. **Nothing depends on one server.** Settings live in `.env`, files go through Laravel's storage layer, background work through Laravel's queue. That is what lets us move hosts without a rewrite.

---

## 2. Hosting stages

We start on the Hostinger plan we already have and move up only when traffic forces it. The code never changes; only `.env` and the server do.

| Stage | Where | Move here when | What changes |
|---|---|---|---|
| **1. Build + Adorna pilot** (now) | Hostinger web hosting (shared) | — | Starting point |
| **2. First real sellers** | Hostinger VPS or DigitalOcean | hPanel shows CPU/memory limits hit, pages slow at busy times, cron jobs time out, or several stores have real traffic | Redis for queue and cache; queue runs as a real worker |
| **3. Large scale** | AWS or Google Cloud | One server is no longer enough | Several app servers, load balancer, managed MySQL, S3 for media |

The domain is registered at **Namecheap**, with DNS and HTTPS on **Cloudflare's free plan** in every stage.

---

## 3. Repository structure

A standard Laravel app, with business code split into one folder per area. Every module has the same shape, so the tenth one looks like the first.

```
minivora/
├── app/
│   ├── Core/                      # shared plumbing; changes need 2 reviewers
│   │   ├── Tenancy/               # ResolveStore middleware, CurrentStore, BelongsToStore trait
│   │   ├── Money/                 # Piasters value object + formatting
│   │   ├── Permissions/           # role matrix from SRS §7.2
│   │   └── AuditLog/              # hash-chained activity log (NFR-SEC-10)
│   │
│   ├── Modules/                   # one folder per business area
│   │   ├── Platform/              # stores, one-step store creation, platform admin   (FR-ONB)
│   │   ├── Accounts/              # users, members, roles, invites, 2FA              (FR-ACC)
│   │   ├── Catalog/               # products, variants, categories, search, reviews  (FR-CAT)
│   │   ├── Cart/                  # carts, cart events, merge on login               (FR-CRT)
│   │   ├── Checkout/              # checkout, leads, discounts, risk flags            (FR-PAY)
│   │   │   └── Gateways/          # Kashier, Paymob, InstaPay, COD, Fawry (test mode)
│   │   ├── Orders/                # statuses, shipping zones, returns, invoices       (FR-ORD)
│   │   ├── Builder/               # pages, sections, themes, media, versions          (FR-BLD)
│   │   ├── Seo/                   # slugs, redirects, sitemaps, JSON-LD, feeds, audit (FR-SEO)
│   │   ├── Marketing/             # pixels, CAPI, newsletter, abandoned carts, Meta   (FR-MKT, FR-MI)
│   │   ├── Analytics/             # page views, KPIs, visit log, demand              (FR-ANA)
│   │   ├── Ledger/                # chart of accounts, posting rules, statements     (FR-LED)
│   │   ├── Fpa/                   # budgets, variances, what-if, forecast            (FR-FPA)
│   │   └── Notifications/         # emails, in-dashboard alerts                      (FR-NTF)
│   │
│   │   # every module has the SAME folders:
│   │   #   Models/          Eloquent models (use the BelongsToStore trait)
│   │   #   Data/            laravel-data objects: validated input + TypeScript types
│   │   #   Http/            controllers (thin: validate → service → response)
│   │   #   Services/        business logic; the ONLY thing other modules may call
│   │   #   Events/, Listeners/
│   │   #   Jobs/            queued and scheduled work
│   │   #   AiFunctions/     what the AI team is allowed to call (optional)
│   │   #   Policies/        permission checks
│   │
│   ├── Ai/                        # AI management team
│   │   ├── Llm/                   # HTTP clients for Gemini/Groq/OpenRouter, key rotation, PII masking
│   │   ├── Registry/              # collects every module's AiFunctions + permission checks
│   │   ├── Understanding/         # question → one function + parameters (FR-AI-02)
│   │   ├── Agents/                # Ceo, Cfo, Cmo, Cro, Coo, Cxo, Cto, Chro, Risk
│   │   ├── Coordinator/           # rules when agents disagree (FR-AI-08)
│   │   ├── Proactive/             # checks, insight cards, "Today" feed, morning brief (FR-PRO)
│   │   ├── Guards/                # number guard (FR-FPA-06)
│   │   └── Eval/                  # evaluation questions + runner (NFR-AIQ)
│   │
│   └── Importers/Adorna/          # one-way import from Adorna EG for the pilot (§2.7)
│
├── database/
│   ├── migrations/                # ALL schema changes go here
│   └── seeders/                   # DemoStoresSeeder: 5 stores × 200 products
│
├── resources/
│   ├── views/storefront/          # Blade: layouts/, pages/, sections/ (one file per builder section)
│   ├── js/                        # React + TypeScript dashboard (Inertia)
│   │   ├── Pages/                 # one folder per dashboard area (SRS §7.1)
│   │   ├── Components/ui/         # Button, Card, Input, Badge, GlassPanel…
│   │   ├── lib/                   # money.ts, i18n.ts
│   │   ├── types/generated.ts     # GENERATED from Data/ classes; do not edit
│   │   └── styles/tokens.css      # GENERATED from design/tokens; do not edit
│   └── lang/ar, lang/en           # every user-facing string
│
├── routes/
│   ├── storefront.php             # shopper pages, resolved by store subdomain
│   ├── dashboard.php              # owner dashboard (Inertia)
│   ├── api.php                    # /api/v1: storefront calls, webhooks, future apps
│   └── console.php                # the schedule: every timed job in one place
│
├── tests/
│   ├── Unit/, Feature/            # per-module tests
│   └── Isolation/                 # proves no store can read another store's data
├── e2e/                           # Playwright: shopper + owner journeys
├── design/tokens/                 # tokens.json from Figma + build script
├── docs/                          # SRS, technical overview, adr/ (decisions)
├── .github/
│   ├── workflows/ci.yml           # tests + linters on every push
│   ├── workflows/deploy.yml       # build + deploy to Hostinger on push to main
│   ├── CODEOWNERS
│   └── PULL_REQUEST_TEMPLATE.md
├── .env.example                   # every setting, documented; real .env never committed
├── composer.json, package.json, vite.config.ts, phpstan.neon, pint.json
└── public/                        # the web root on the server
```

---

## 4. How the pieces connect

Each connection is a rule. Respect it and your part plugs in at the end without anyone changing their code.

### 4.1 Finding the store

```
adorna.<domain> → Cloudflare wildcard DNS → Hostinger → ResolveStore middleware
                                                          │ finds the "adorna" row in stores
                                                          ▼
                                    CurrentStore is set → every model query adds WHERE store_id = …
```

- **Storefront:** the store comes from the address (subdomain, or the seller's own domain later).
- **Dashboard:** the store comes from the logged-in member's selected store.
- Models get scoping by using the `BelongsToStore` trait. Forgetting it fails the isolation test in CI.
- Reserved names that can never be store names: `app`, `api`, `www`, `mail`, `admin`, `status`.

### 4.2 Module ↔ module: services and events, never tables

- A module **may** call another module's `Services/`.
- A module **must not** use another module's `Models/` or query its tables.
- For "when X happens, Y reacts", fire a Laravel event:

| Event | Fired by | Handled by |
|---|---|---|
| `OrderPaid` | Checkout | Ledger (post sale), Notifications, Analytics, Proactive |
| `OrderShipped` | Orders | Ledger (post cost of goods), Notifications |
| `OrderCancelled` / `OrderReturned` | Orders | Catalog (restock), Ledger (reverse), Notifications |
| `RefundIssued` | Orders | Ledger, Notifications, Proactive |
| `StockBelowReorder` | Catalog | Proactive (COO) |
| `PaymentFailed` | Checkout | Proactive (CFO, Risk) |
| `AdSpendRecorded` | Marketing | Ledger (marketing expense) |
| `PagePublished` | Builder | Seo (sitemap, feeds, IndexNow) |
| `SlugChanged` | Catalog / Builder | Seo (301 redirect) |

Slow listeners (emails, AI calls) are queued so the shopper never waits for them.

### 4.3 AI team ↔ everything else: the function registry

```
owner question → Ai/Understanding → picks ONE function → Ai/Registry
                                                           │ checks the asking user's role
                                                           ▼
                                           Modules/<X>/AiFunctions → Services
```

- Module owners decide what the AI may do in their `AiFunctions/`. Each function declares its permission and whether it **needs owner approval** (FR-AI-07: spending, prices, refunds, publishing, messaging customers).
- The AI team never writes queries and never formats money itself.
- Until modules are ready, AI devs register **stub functions** with the same signatures.

### 4.4 Laravel ↔ React: Inertia + generated types

Controllers return `Inertia::render('Orders/Index', $data)`. The props are `laravel-data` objects, and their TypeScript types are generated:

```bash
php artisan typescript:transform   # Data/ classes → resources/js/types/generated.ts
```

Change a `Data` class → regenerate → commit both. CI fails if the generated file is stale.

### 4.5 Scheduled work: one cron line

`routes/console.php` lists every timed job. On Hostinger, one cron line runs it every minute, and queued jobs are processed in short batches inside it:

| When | Jobs |
|---|---|
| Every minute | process the queue (`queue:work --stop-when-empty --max-time=50`) |
| Hourly | proactive checks: orders, payments, stock (FR-PRO-01) |
| Daily | sales, traffic, ads and cash checks; Meta import; gateway reconcile; data pruning |
| 09:00 Cairo | morning brief (FR-PRO-06) |
| Nightly | Adorna import, FP&A memos (ready by 07:00), backup to Backblaze B2 |
| Weekly | margin, trend and budget checks; weekly briefing; backup restore test |

Every job finishes in under a minute (split big work into batches) and is safe to run twice.

### 4.6 AI providers

`app/Ai/Llm` calls the providers directly with Laravel's HTTP client:

```env
GEMINI_API_KEYS=key1,key2
GROQ_API_KEYS=key1,key2
OPENROUTER_API_KEYS=key1
```

When a key hits its rate limit, the next key or provider is used automatically (FR-AI-11). Names, phones and addresses are masked before every call (NFR-PRV-03).

### 4.7 Figma ↔ dashboard: design tokens

```bash
npm run tokens   # design/tokens/tokens.json → resources/js/styles/tokens.css
```

Use `var(--mv-*)` variables. Never hardcode colors, spacing or radii.

---

## 5. Who works where

Fill in names and mirror this in `.github/CODEOWNERS`. One owner per area reviews its PRs; anyone may contribute.

| Area | Folders | SRS | Owner |
|---|---|---|---|
| Core, tenancy, CI, deploy | `app/Core/`, `Modules/Platform/`, `Modules/Accounts/`, `.github/` | FR-ACC, FR-ONB, NFR-SEC | _TBD_ |
| Catalog, cart, checkout | `Modules/Catalog/`, `Cart/`, `Checkout/` | FR-CAT, FR-CRT, FR-PAY | _TBD_ |
| Orders, notifications | `Modules/Orders/`, `Notifications/` | FR-ORD, FR-NTF | _TBD_ |
| Storefront, SEO | `resources/views/storefront/`, `Modules/Seo/` | FR-SEO, NFR-PER | _TBD_ |
| Builder, themes, media | `Modules/Builder/`, `resources/js/Pages/Builder/` | FR-BLD | _TBD_ |
| Ledger, FP&A, analytics | `Modules/Ledger/`, `Fpa/`, `Analytics/` | FR-LED, FR-FPA, FR-ANA | _TBD_ |
| Marketing | `Modules/Marketing/` | FR-MKT, FR-MI | _TBD_ |
| AI team | `app/Ai/` | FR-AI, FR-PRO, NFR-AIQ | _TBD_ |
| Dashboard shell, design system | `resources/js/`, `design/` | §7.1, NFR-USE | _TBD_ |
| Adorna importer | `app/Importers/Adorna/` | §2.7 | _TBD_ |

Shared files (`app/Core/`, `database/migrations/`, `.env.example`, `.github/workflows/`) need **two approvals**.

---

## 6. Run it on your computer

**You need:** PHP 8.3+, Composer, Node 20+, MySQL. The easiest way on Windows or macOS is [Laravel Herd](https://herd.laravel.com) (free) or Laragon/XAMPP.

```bash
git clone https://github.com/Ahmedtamer-1/Minivora.git
cd Minivora
composer install
npm install
cp .env.example .env              # then fill in DB settings and keys from Bitwarden
php artisan key:generate
php artisan migrate --seed        # creates tables + 5 demo stores
composer run dev                  # starts the app, queue worker and Vite together
```

| Address | What |
|---|---|
| http://localhost:8000 | Landing / sign up |
| http://app.localhost:8000 | Owner dashboard |
| http://demo.localhost:8000 | A seeded storefront |
| http://localhost:8000/docs/api | API documentation |

Browsers send every `*.localhost` address to your own computer, so store subdomains work locally with no setup.

---

## 7. Deploy to Hostinger

A push to `main` deploys automatically once CI is green. `.github/workflows/deploy.yml`:

1. installs PHP and Node, runs the tests;
2. builds the dashboard (`npm run build`), so the server never needs Node;
3. copies the app to Hostinger over SSH;
4. runs `composer install --no-dev`, `php artisan migrate --force`, `php artisan optimize` on the server.

**One-time server setup** (the platform owner does this once):

| Where | Setting | Value |
|---|---|---|
| Namecheap | Nameservers | Cloudflare's two nameservers |
| Cloudflare DNS | `<domain>`, `www`, `*` | Hostinger server IP, proxied |
| Cloudflare DNS | `MX` + mail records | Hostinger mail, not proxied |
| Cloudflare SSL | Mode | Full |
| hPanel | Website | `<domain>`, document root = the app's `public` folder |
| hPanel | Subdomain `*` | Same folder as the main site |
| hPanel | Databases | Create the MySQL database and user |
| hPanel | SSH | Enable, add the deploy key; store it as a GitHub secret |
| hPanel | Cron (every minute) | `cd ~/minivora && php artisan schedule:run >> /dev/null 2>&1` |
| Server | `.env` | Production values; never committed |

On any other cPanel host the steps are the same: document root, a `*` subdomain, a MySQL database, SSH and one cron line.

---

## 8. Team rules

| Rule | In practice |
|---|---|
| Branch per Jira issue | `feat/SCRUM-102-design-tokens`; `main` is protected: green CI + 1 review |
| Commits start with the Jira key | `SCRUM-102 add glass tokens` links the commit in Jira |
| Every table has `store_id` | Model uses `BelongsToStore`, and the isolation test covers it |
| Money is integer piasters | Never floats, anywhere |
| Validate with `Data` classes | No raw arrays from requests; TypeScript types come from them |
| Strict TypeScript | `strict: true`, no `any` |
| Migrations only | Never change the database by hand |
| No secrets in Git | Keys go in `.env` and Bitwarden; add new settings to `.env.example` |
| All text translated | `lang/ar` and `lang/en`; layouts mirror correctly right-to-left |
| No copies or dead code | No `file (1).php`, no commented-out blocks (NFR-MNT-04) |

---

## 9. Testing

| What | Tool | Where |
|---|---|---|
| Module logic | Pest | `tests/Unit`, `tests/Feature` |
| Store isolation | Pest | `tests/Isolation` |
| Dashboard components | Vitest | next to the component |
| Shopper and owner journeys | Playwright | `e2e/` |
| AI answer quality | eval runner | `app/Ai/Eval` |
| Code quality | PHPStan, Pint, ESLint, `tsc` | CI |

Name every test after the requirement it proves, so the traceability table builds itself:

```php
test('FR-LED-03 rejects an unbalanced journal entry', function () { … });
```

CI gates: ledger posting rules and business rules **100%** covered, overall **≥ 70%** (NFR-MNT-02). Tests never call real AI, payment or email services; they use fakes.

---

## 10. Build order

Each step works end to end before the next starts.

1. **Foundation:** domain + Cloudflare + Hostinger setup, deploy a "hello world" on day one; then `app/Core` (tenancy, permissions, money, audit log), CI, design tokens, dashboard shell.
2. **Store creation:** one-step sign-up, store subdomains, demo stores.
3. **Commerce:** catalog → cart → checkout (COD first, then Kashier test mode) → orders → emails.
4. **Storefront + SEO:** Blade pages, Arabic and English URLs, sitemaps, JSON-LD, feeds.
5. **Builder:** sections, themes, media, versions.
6. **Ledger + analytics:** posting rules on events, statements, KPIs.
7. **Adorna importer:** pilot data in place.
8. **AI team:** registry over finished modules, question understanding, agents, proactive checks, FP&A.

Steps 1–7 are the non-AI core sprint. The AI team can start step 8 early against stub functions (section 4.3).
