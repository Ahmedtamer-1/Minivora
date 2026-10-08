# Minivora

**An AI-native, multi-store e-commerce engine for small Egyptian sellers.**
A seller describes the business in Arabic or English, an AI team builds the store, the store sells, and the owner runs it with finance-grade answers backed by a double-entry ledger.

> The full requirements live in [`docs/SRS.docx`](docs/SRS.docx). Every requirement has an ID (`FR-CAT-01`, `NFR-SEC-05`, …). Reference those IDs in branches, commits, PRs and tests so we can trace every result back to the SRS at the defense.

> **Hosting rule: free cloud services, no credit card, no self-hosted laptops.** The only paid things are what the team already owns: the **Minivora domain** and a **Hostinger web hosting** plan (landing page, mailboxes, cron). If you want to add a service, check section 2 first and get team approval.

---

## Table of contents

1. [Architecture at a glance](#1-architecture-at-a-glance)
2. [The free cloud stack](#2-the-free-cloud-stack)
3. [Repository structure](#3-repository-structure)
4. [How the pieces connect](#4-how-the-pieces-connect)
5. [Who works where](#5-who-works-where)
6. [Getting started](#6-getting-started)
7. [Team conventions](#7-team-conventions)
8. [Testing and requirement traceability](#8-testing-and-requirement-traceability)
9. [Build order](#9-build-order)
10. [SRS changes caused by free cloud hosting](#10-srs-changes-caused-by-free-cloud-hosting)

---

## 1. Architecture at a glance

```
                ┌──────────────────── Cloudflare (free: DNS for <domain>, HTTPS, edge cache) ────────────────────┐
 Visitors ─────►│  <domain>, www.<domain>  ──► Hostinger web hosting: landing page                               │
 Shoppers ─────►│  <store>.<domain>        ──► Cloudflare Worker ──► Koyeb: FastAPI backend ──► storefront (SSR)  │
 Owners ───────►│  app.<domain>            ──► Cloudflare Pages: React dashboard ──(/api/* proxy)──► Koyeb       │
                └─────────────────────────────────────────────────────────────────────────────────────────────────┘
                                                     │
          ┌──────────────────────┬───────────────────┼────────────────────┬─────────────────────┐
          ▼                      ▼                   ▼                    ▼                     ▼
   Neon Postgres +        Cloudinary            Backblaze B2      Cloud LLMs via LiteLLM      Brevo SMTP
   pgvector + RLS       (product media,       (receipts, PDFs,    (Groq, Gemini, OpenRouter,   (emails)
                        WebP/AVIF, srcset)     exports, backups)   keys rotated)

   Hostinger cron: on-time scheduled jobs (proactive checks, memos, imports) + keeps the backend awake
   GitHub Actions: CI on every push · nightly backups · backup trigger for scheduled jobs
   UptimeRobot: uptime monitoring and alerts
```

Four rules shape the whole codebase:

1. **Multi-tenant.** Every table except `stores` and `users` has a `store_id`, and RLS makes cross-store reads impossible (NFR-SEC-05).
2. **Money is integer piasters.** 1 EGP = 100 piasters, everywhere: DB, API and TypeScript. Never floats.
3. **AI never touches the database.** Agents call the same permission-checked service functions the dashboard uses (FR-AI-06). LLMs never produce financial figures; code does (FR-AI-03, FR-FPA-06).
4. **Every money event posts to the ledger.** Orders, refunds, fees and ad spend create balanced journal entries (FR-LED-02, FR-LED-03).

Plus one rule from the hosting choice:

5. **The backend is stateless and cheap.** No local disk, no long-running background threads, no heavy image processing on the server. Files go to Cloudinary/B2, jobs run as HTTP calls from GitHub Actions, and anything slow is split into small idempotent steps.

---

## 2. The free cloud stack

All cloud tiers below are free without a credit card, as checked in October 2026; the last two rows are plans the team already owns. Free tiers change: re-check before relying on a limit, and log changes in `docs/adr/`.

| Need | Service | Free limits that matter | How we live with them |
|---|---|---|---|
| Backend (API + storefront) | **Koyeb** free instance, Frankfurt | 1 service, 512 MB RAM, very small CPU share; sleeps after **1 hour** without traffic, wakes in seconds | Hostinger cron hits `/healthz` every 10 min; storefront HTML cached at Cloudflare's edge |
| Backup backend host | **Render** free web service | Sleeps after 15 min, ~1 min to wake | Same Docker image; switch only if Koyeb changes its free tier |
| Database | **Neon** Postgres, Frankfurt | **0.5 GB storage**, 100 compute-hours per project per month, sleeps after 5 min idle, 10 branches, 6-hour restore window | `/healthz` never touches the DB (so Neon can sleep); media stays out of the DB; old page views pruned; one Neon branch per developer |
| Dashboard hosting | **Cloudflare Pages** | Unlimited static bandwidth; Pages Functions have a daily request cap | Static build + one tiny function that proxies `/api/*` to Koyeb |
| DNS, HTTPS, CDN | **Cloudflare** free plan | Free certificate covers `<domain>` and **one** subdomain level | HTTPS + HSTS (NFR-SEC-01), caches storefront pages; stores are always `<store>.<domain>` |
| Store subdomains → backend | **Cloudflare Worker** (free) | Daily request cap | Wildcard `*.<domain>` → Worker forwards to Koyeb with the original host in `X-Forwarded-Host` |
| Product images and video | **Cloudinary** free | 25 monthly credits shared across storage, bandwidth and transformations | Cloudinary does WebP/AVIF + resizing (FR-CAT-08), so our tiny server never converts images |
| Private files and backups | **Backblaze B2** | 10 GB storage | Receipts, invoices, exports, nightly DB dumps; S3-compatible, so the provider is just env vars |
| AI models | **Groq, Google AI Studio (Gemini), OpenRouter** free keys via LiteLLM | Per-key rate limits | LiteLLM runs **inside** the backend and rotates keys on rate limits (FR-AI-11); several team keys configured |
| Embeddings (semantic search) | Gemini embeddings free tier | Rate limits | Computed once on product save, stored in pgvector |
| Email | **Brevo** free | 300 emails/day | Enough for demo stores; recovery emails batched |
| Scheduled jobs | **Hostinger cron** (primary), GitHub Actions cron (backup) | Shared-hosting cron, fine for `curl` calls | Jobs are short HTTP calls to the backend, never long scripts |
| CI | **GitHub Actions** | Same minutes pool | Cache dependencies; run Playwright only on PRs to `main` |
| Uptime monitoring | **UptimeRobot** free | 5-minute checks | Alerts + evidence for NFR-REL-04 |
| Errors (optional) | **Sentry** free | Monthly event cap | Backend + dashboard |
| Secrets for the team | **Bitwarden** free organization | — | All keys live here, never in chat or Git |
| Domain | **Minivora domain** (team-owned) | Renewal price, not promo price, is what matters | Nameservers point to Cloudflare; registrar only renews it |
| Landing page, mailboxes, cron | **Hostinger web hosting** (team-owned) | Shared hosting: static files + PHP only, no Python/Docker | Landing page at `<domain>`, inboxes like `hello@` and `support@`, cron jobs. **Never** runs app code |
| Spare test hosting | **Namecheap shared hosting** (team-owned) | Same limits as above | Sandbox for trying landing-page or email changes before Hostinger; nothing depends on it |

**Store addresses.** With the domain, every store gets `<store>.<domain>` (FR-ONB-02). Until the domain is bought and its DNS moved to Cloudflare:

- the dashboard runs on `minivora.pages.dev`;
- storefronts use **path-based addresses** on the backend: `https://<app>.koyeb.app/s/<store>/ar/...`.

`core/tenancy.py` supports both, so switching is a config change (`STORE_HOST_MODE=subdomain`, `ROOT_DOMAIN=<domain>`), not a rewrite. Because of the one-level certificate limit, never nest stores under another subdomain.

---

## 3. Repository structure

This is a **monorepo**: one Git repo, one CI pipeline, auto-deploys from `main`.

```
minivora/
├── README.md
├── .env.example                  # every variable documented; real .env is NEVER committed
├── Makefile                      # make install | dev | test | lint | migrate | seed | api-client | tokens
├── docker-compose.yml            # OPTIONAL: local Postgres for offline work only (not used in hosting)
│
├── .github/
│   ├── workflows/
│   │   ├── ci.yml                # ruff, mypy, pytest, eslint, tsc, vitest, playwright
│   │   ├── jobs.yml              # BACKUP scheduler: same job calls as Hostinger cron, used if it fails
│   │   ├── backup.yml            # nightly pg_dump → B2, weekly restore test into a Neon branch
│   │   └── landing.yml           # deploys landing/ to Hostinger on push
│   ├── CODEOWNERS                # maps folders to owners (see section 5)
│   ├── PULL_REQUEST_TEMPLATE.md
│   └── dependabot.yml            # NFR-SEC-11
│
├── backend/                      # Python, FastAPI: API + storefront, deployed to Koyeb
│   ├── pyproject.toml            # ruff, mypy, pytest config
│   ├── Dockerfile                # the ONLY deploy artifact (same image on Koyeb or Render)
│   ├── alembic/versions/         # ALL schema changes go here (NFR-MNT-03)
│   ├── app/
│   │   ├── main.py               # creates the app, mounts api/v1, storefront, internal jobs, /healthz
│   │   ├── core/                 # shared plumbing; changes need 2 reviewers
│   │   │   ├── config.py         # pydantic-settings, reads env vars
│   │   │   ├── db.py             # async engine (Neon pooled URL), sets app.store_id for RLS
│   │   │   ├── tenancy.py        # resolves the store from host OR /s/<store>/ path, or from JWT
│   │   │   ├── security.py       # Argon2id, JWT, CSRF, rate limits
│   │   │   ├── permissions.py    # role matrix from SRS §7.2
│   │   │   ├── events.py         # in-process domain event bus
│   │   │   ├── storage.py        # Cloudinary (public media) + B2 (private files) behind one interface
│   │   │   ├── email.py          # Brevo SMTP
│   │   │   ├── money.py          # Piasters type + formatting
│   │   │   ├── i18n.py           # ar/en, RTL, Cairo time, digit style
│   │   │   └── activity_log.py   # hash-chained audit log (NFR-SEC-10)
│   │   │
│   │   ├── modules/              # one folder per business domain
│   │   │   ├── platform/         # stores, one-step store creation, platform admin  (FR-ONB, FR-ACC-08)
│   │   │   ├── accounts/         # users, members, roles, invites, 2FA            (FR-ACC)
│   │   │   ├── catalog/          # products, variants, categories, search, reviews (FR-CAT)
│   │   │   ├── cart/             # carts, cart events, merge on login             (FR-CRT)
│   │   │   ├── checkout/         # checkout, leads, discount codes, risk flags     (FR-PAY)
│   │   │   │   └── gateways/     # kashier.py, paymob.py, instapay.py, cod.py, fawry.py (all test mode)
│   │   │   ├── orders/           # statuses, shipping zones, returns, invoices     (FR-ORD)
│   │   │   ├── builder/          # pages, sections, themes, media, versions        (FR-BLD)
│   │   │   ├── seo/              # slugs, redirects, sitemaps, robots, JSON-LD, feeds, audit (FR-SEO)
│   │   │   ├── marketing/        # pixels, CAPI, newsletter, abandoned checkouts, ad spend (FR-MKT)
│   │   │   ├── marketing_intel/  # Meta import, campaign verdicts, proposals      (FR-MI)
│   │   │   ├── analytics/        # page views, KPIs, visit log, demand            (FR-ANA)
│   │   │   ├── ledger/           # chart of accounts, posting rules, statements   (FR-LED)
│   │   │   ├── fpa/              # budgets, variances, what-if, forecast          (FR-FPA)
│   │   │   └── notifications/    # emails, in-dashboard alerts                    (FR-NTF)
│   │   │
│   │   │   # every module has the SAME shape:
│   │   │   #   models.py       SQLAlchemy tables (all with store_id)
│   │   │   #   schemas.py      Pydantic v2 request/response models
│   │   │   #   service.py      business logic; the ONLY public entry point
│   │   │   #   router.py       /api/v1/<module> endpoints (thin: validate → service)
│   │   │   #   events.py       events this module emits / listens to
│   │   │   #   jobs.py         scheduled work this module needs (optional, see 4.6)
│   │   │   #   ai_functions.py functions exposed to the AI team (optional)
│   │   │   #   tests/
│   │   │
│   │   ├── storefront/           # server-rendered shop (FR-SEO-09)
│   │   │   ├── routes.py         # /ar/... and /en/... pages (+ /s/<store>/ prefix without a domain)
│   │   │   ├── templates/        # Jinja2: layouts, pages, partials
│   │   │   │   └── sections/     # one template per builder section type (FR-BLD-01)
│   │   │   └── static/           # < 100 KB JS per page (NFR-PER-03)
│   │   │
│   │   ├── ai/                   # AI management team
│   │   │   ├── llm/              # LiteLLM client + models.yaml, key rotation, PII masking (always on)
│   │   │   ├── functions/        # registry: collects every module's ai_functions.py + permission check
│   │   │   ├── understanding/    # question → one function + params (FR-AI-02)
│   │   │   ├── agents/           # ceo/, cfo/, cmo/, cro/, coo/, cxo/, cto/, chro/, risk/
│   │   │   ├── coordinator/      # conflict rules between agents (FR-AI-08)
│   │   │   ├── proactive/        # checks, insight cards, "Today" feed, morning brief (FR-PRO)
│   │   │   ├── guards/           # number guard (FR-FPA-06)
│   │   │   └── eval/             # evaluation set + runner (NFR-AIQ)
│   │   │
│   │   ├── jobs/                 # /internal/jobs/<name>: registry of every module's jobs.py, secret-protected
│   │   └── api/v1/router.py      # mounts every module's router under /api/v1
│   │
│   ├── importers/adorna/         # one-way MySQL → Minivora importer (FR-ONB-09, §2.7), run from GitHub Actions
│   ├── seeds/                    # 5 demo stores, 200 products each (§2.4)
│   └── tests/                    # cross-module: RLS isolation, ledger balance, flows
│
├── dashboard/                    # TypeScript, React; deployed to Cloudflare Pages
│   ├── package.json
│   ├── functions/api/[[path]].ts # Pages Function: proxies /api/* to Koyeb (same origin, no CORS)
│   ├── src/
│   │   ├── app/                  # router, providers, layout shell, auth guard
│   │   ├── features/             # mirrors SRS §7.1 dashboard areas
│   │   │   ├── onboarding/  home/  ai-chat/  approvals/  products/  orders/
│   │   │   ├── customers/  builder/  marketing/  seo/  finance/  settings/
│   │   │   └── platform-admin/
│   │   │       # each feature: pages/, components/, hooks/ (data fetching), *.test.tsx
│   │   ├── components/ui/        # shared design-system components (Button, Card, Input, Badge, GlassPanel…)
│   │   ├── lib/
│   │   │   ├── api/schema.ts     # GENERATED from OpenAPI; do not edit by hand
│   │   │   ├── api/client.ts     # typed fetch wrapper, JWT refresh
│   │   │   ├── money.ts          # piasters ↔ display
│   │   │   └── i18n.ts           # ar/en, RTL switch
│   │   ├── locales/ar.json, en.json
│   │   └── styles/
│   │       ├── tokens.css        # GENERATED from design/tokens; do not edit by hand
│   │       └── tokens.ts         # GENERATED typed token names
│   └── tests/
│
├── landing/                      # static marketing site → uploaded to Hostinger (public_html)
│   └── deploy.md                 # how it's uploaded (Git deploy in hPanel or GitHub Action via FTP)
│
├── design/
│   ├── tokens/                   # tokens.json exported from Figma (SCRUM-102)
│   ├── build-tokens.mjs          # tokens.json → dashboard/src/styles/tokens.{css,ts}
│   └── README.md                 # Figma file link + how to re-export
│
├── e2e/                          # Playwright: shopper + owner journeys (SRS §9.3)
├── load/                         # k6 scripts; small runs only on free tiers
├── infra/
│   ├── koyeb/service.yaml        # Koyeb service definition (region, env var names, health check)
│   ├── render/render.yaml        # fallback host, same image
│   ├── cloudflare/
│   │   ├── README.md             # DNS records (section 4.8), cache rules, Pages setup
│   │   └── store-router/         # the Worker that sends <store>.<domain> to Koyeb
│   ├── hostinger/cron.md         # the exact cron lines configured in hPanel
│   └── accounts.md               # every free account we use, who owns it, where its keys live
│
└── docs/
    ├── SRS.docx
    ├── architecture.md
    ├── traceability.md           # requirement ID → code → test (generated)
    └── adr/                      # architecture decision records, one file per decision
```

---

## 4. How the pieces connect

Each connection below is a **contract**. If you respect the contract, your part plugs in at the end without anyone else changing their code.

### 4.1 Backend ↔ Dashboard: the OpenAPI schema

FastAPI publishes `/api/v1/openapi.json` automatically from the Pydantic schemas. The dashboard **generates** its types from it:

```bash
make api-client    # backend running → regenerates dashboard/src/lib/api/schema.ts
```

- Backend devs change `schemas.py` → run `make api-client` → commit the regenerated file in the same PR.
- Frontend devs never hand-write API types. If a field is missing, ask the module owner to add it to the schema.
- CI fails if `schema.ts` is out of date with the backend.
- The dashboard calls `/api/*` on its own origin; the Pages Function forwards to Koyeb. Auth is a short-lived JWT access token + refresh token (SRS §7.3).

### 4.2 Module ↔ Module: services and events, never tables

- A module **may** call another module's `service.py` functions.
- A module **must not** import another module's `models.py` or query its tables.
- For "when X happens, Y should react", emit a **domain event** through `core/events.py`:

| Event | Emitted by | Listened to by |
|---|---|---|
| `order.paid` | checkout | ledger (post sale), notifications (email), analytics, proactive |
| `order.shipped` | orders | ledger (post COGS), notifications |
| `order.cancelled` / `order.returned` | orders | catalog (restock), ledger (reverse), notifications |
| `refund.issued` | orders | ledger, notifications, proactive |
| `stock.below_reorder` | catalog | proactive (COO) |
| `payment.failed` | checkout | proactive (CFO, Risk) |
| `ad_spend.recorded` | marketing | ledger (marketing expense) |
| `page.published` | builder | seo (sitemap, feeds, IndexNow) |
| `slug.changed` | catalog / builder | seo (301 redirect) |

Event handlers run **inside the same database transaction** as the action that caused them. There is no separate queue (we have no free worker), so handlers must be fast. Anything slow (emails, AI calls) is written to an `outbox` table and sent by a scheduled job.

### 4.3 AI team ↔ Everything else: the function registry

```
owner question → ai/understanding → picks ONE function → ai/functions/registry
                                                            │ checks the asking user's role (SRS §7.2)
                                                            ▼
                                            modules/<x>/ai_functions.py → service.py
```

- Each module owner exposes what the AI may do in their module's `ai_functions.py` (read functions, plus draft/propose functions).
- Each function declares its **permission** and whether it **needs approval** (FR-AI-07: spending, prices, refunds, publishing, messaging customers).
- The AI team builds agents against the registry. It never imports models, never writes SQL, never formats money itself.
- Until the real modules exist, AI devs can register **stub functions** with the same signatures and swap them later.

### 4.4 Storefront ↔ Modules: in-process calls

The storefront lives inside the backend, so its routes call module services directly (no HTTP round-trip). Section templates in `storefront/templates/sections/` must match section types defined in `modules/builder/` one-to-one. Pages send `Cache-Control` headers so Cloudflare can serve repeat visits without waking the backend.

### 4.5 Backend ↔ AI: LiteLLM inside the backend

There is no AI server. `ai/llm/` calls cloud models through LiteLLM as a Python library, configured in `ai/llm/models.yaml`:

```env
GROQ_API_KEYS=key1,key2
GEMINI_API_KEYS=key1,key2
OPENROUTER_API_KEYS=key1
```

- When one key hits its rate limit, the next is used automatically (FR-AI-11).
- **Personal data is masked before every call**, because every model is now a cloud model (NFR-PRV-03). Only aggregated store figures and masked text leave our backend.

### 4.6 Scheduled work: Hostinger cron → `/internal/jobs/*`

There is no always-on worker on any free tier, so a **cron job on the Hostinger plan** calls the backend on a schedule. Hostinger cron runs on time, which matters for the 09:00 morning brief (FR-PRO-06). `.github/workflows/jobs.yml` holds the same calls as a backup. The exact lines live in `infra/hostinger/cron.md`:

```
POST https://<backend>/internal/jobs/<name>     header: X-Jobs-Secret: $JOBS_SECRET
```

| Schedule | Jobs |
|---|---|
| Every 15 min | send outbox (emails, alerts), event-driven proactive checks |
| Hourly | proactive checks: orders, payments, stock (FR-PRO-01) |
| Daily | sales/traffic/ads/cash checks, Meta import, gateway reconcile, morning brief, data pruning |
| Nightly | Adorna import, FP&A memos (ready by 07:00 Cairo, NFR-PER-07), backups |
| Weekly | margins/trends/budget checks, weekly briefing, restore test |

Rules: each job **finishes in under 60 seconds** (split big work into batches and call it again), and each job is **idempotent**, because a schedule may fire late or twice (for example when both cron sources run). The backend records each run in a `job_runs` table and skips a job already done for that slot.

### 4.7 Figma ↔ Dashboard: design tokens

```bash
make tokens   # design/tokens/tokens.json → dashboard/src/styles/tokens.{css,ts}
```

Use `var(--mv-*)` variables or the typed `tokens.ts` names. Never hardcode colors, spacing or radii in components.

### 4.8 The whole thing: deploys

| Push to `main` | What happens |
|---|---|
| `backend/**` | Koyeb rebuilds the Docker image and deploys; `alembic upgrade head` runs on start |
| `dashboard/**` | Cloudflare Pages builds and deploys |
| `landing/**` | GitHub Action uploads the landing site to Hostinger |
| any | GitHub Actions CI must be green first (branch protection) |

**DNS records on Cloudflare** (the domain's nameservers point to Cloudflare):

| Record | Points to | Proxied | Serves |
|---|---|---|---|
| `<domain>`, `www` | Hostinger web hosting IP | Yes | Landing page (`www` redirects to the root) |
| `app` | Cloudflare Pages project | Yes | Dashboard; `/api/*` forwarded to Koyeb |
| `api` | Koyeb app | Yes | Webhooks (`/webhooks/*`) and direct API access |
| `*` (wildcard) | Cloudflare Worker `store-router` → Koyeb | Yes | Every storefront: `<store>.<domain>` |
| `MX`, mail `TXT` | Hostinger mail servers | No | Team inboxes |
| Brevo `TXT` (SPF, DKIM, DMARC) | Brevo | No | Store emails sent as `noreply@<domain>` without landing in spam |

Specific records (`app`, `api`, `www`) always win over the wildcard, and reserved names (`app`, `api`, `www`, `mail`, `admin`, `status`) can never be chosen as store names (FR-ONB-02 availability check).

---

## 5. Who works where

Fill in names and mirror this table in `.github/CODEOWNERS`. One owner per area; anyone may contribute, but the owner reviews.

| Area | Folders | SRS sections | Owner |
|---|---|---|---|
| Platform core, auth, tenancy, CI, cloud accounts | `backend/app/core/`, `modules/platform/`, `modules/accounts/`, `.github/`, `infra/` | FR-ACC, FR-ONB, NFR-SEC | _TBD_ |
| Catalog, cart, checkout, payments | `modules/catalog/`, `cart/`, `checkout/` | FR-CAT, FR-CRT, FR-PAY | _TBD_ |
| Orders, shipping, notifications | `modules/orders/`, `notifications/` | FR-ORD, FR-NTF | _TBD_ |
| Storefront + SEO | `backend/app/storefront/`, `modules/seo/` | FR-SEO, NFR-PER | _TBD_ |
| Builder, themes, media | `modules/builder/`, `dashboard/src/features/builder/` | FR-BLD | _TBD_ |
| Ledger, FP&A, analytics | `modules/ledger/`, `fpa/`, `analytics/` | FR-LED, FR-FPA, FR-ANA | _TBD_ |
| Marketing + marketing intelligence | `modules/marketing/`, `marketing_intel/` | FR-MKT, FR-MI | _TBD_ |
| AI team | `backend/app/ai/` | FR-AI, FR-PRO, NFR-AIQ | _TBD_ |
| Dashboard shell + design system | `dashboard/src/app/`, `components/ui/`, `design/` | §7.1, NFR-USE | _TBD_ |
| Adorna importer + pilot | `backend/importers/adorna/` | §2.7, FR-ONB-09 | _TBD_ |

Shared files (`core/`, `alembic/`, `.env.example`, `.github/workflows/`) need **two approvals**.

The platform owner creates all free accounts under a shared team email (a Hostinger mailbox such as `team@<domain>` once the domain is live), records them in `infra/accounts.md`, and stores every key in the team Bitwarden. Nobody uses a personal account for production services.

---

## 6. Getting started

**You need:** Git, Python 3.12+, Node 20+. Docker is optional.

```bash
git clone <repo-url> minivora && cd minivora
cp .env.example .env              # fill in from Bitwarden; never commit .env
make install                      # backend venv + dashboard npm install
```

**Your database:** ask the platform owner for **your own Neon branch** (`dev-<yourname>`), and put its URL in `DATABASE_URL`. Branches are copies of the dev data, so you can break yours without affecting anyone. (Offline alternative: `docker compose up db` and use the local URL.)

```bash
make migrate                      # alembic upgrade head on your branch
make seed                         # 5 demo stores
make dev                          # backend on :8000 + dashboard on :5173
```

| URL | What |
|---|---|
| http://localhost:8000/api/v1/docs | Swagger (OpenAPI) |
| http://localhost:5173 | Dashboard |
| http://localhost:8000/s/demo/ar/ | A seeded storefront |

Tests use their own throwaway database: in CI a Postgres container, locally `TEST_DATABASE_URL` (a Neon `test-<yourname>` branch or local Docker).

---

## 7. Team conventions

**Branches**: `main` is protected (PR + green CI + 1 review) and auto-deploys. Branch from `main` using the Jira key:

```
feat/SCRUM-102-design-tokens
fix/SCRUM-131-cod-fee-rounding
```

**Commits**: start with the Jira key so Jira's Development panel links them: `SCRUM-102 add glass tokens to tokens.json`.

**Pull requests**: one Jira issue per PR, list the SRS requirement IDs it covers, include screenshots for UI changes.

**Python** (backend): `ruff` (lint + format) and `mypy --strict` on `app/`. Every request/response body is a Pydantic v2 model in `schemas.py`; no raw `dict` responses.

**TypeScript** (dashboard): `strict: true`, ESLint + Prettier, no `any`. API types come only from the generated `schema.ts`.

**Database**: every new table has `store_id` + an RLS policy + a line in the RLS isolation test. Money columns are `BIGINT` piasters. Schema changes only via Alembic. Remember the **0.5 GB** limit: no files or base64 blobs in the database, and any log-style table needs a pruning job.

**Free-tier hygiene**: no polling loops, no `sleep`-based background threads, no image or video processing on the server, and every external call has a timeout. If a feature needs more than a free tier gives, write an ADR before building it.

**Secrets**: never in code, Git or zip archives (NFR-SEC-06). Add new variables to `.env.example` with a comment, and the value to Bitwarden.

**Repo hygiene**: no duplicate or "copy" files (`auth (1).php`-style), no dead code (NFR-MNT-04).

**Language**: all user-facing text goes through i18n (`locales/ar.json`, `en.json`, or Jinja `_()`). Layouts must mirror correctly in RTL.

---

## 8. Testing and requirement traceability

| Layer | Tool | Lives in |
|---|---|---|
| Unit / module | pytest | `backend/app/modules/<x>/tests/` |
| Cross-module (RLS, ledger, flows) | pytest | `backend/tests/` |
| Dashboard components | Vitest + Testing Library | next to the component |
| Browser journeys | Playwright | `e2e/` |
| Load | k6 (small runs only; free tiers will throttle) | `load/` |
| AI quality | eval runner (compares the free cloud models) | `backend/app/ai/eval/` |

Tag every test with the requirement it proves:

```python
@pytest.mark.req("FR-LED-03")
def test_unbalanced_entry_is_rejected(): ...
```

```ts
test("FR-PAY-02 rejects non-Egyptian mobile numbers", ...)
```

`make traceability` scans these tags and regenerates `docs/traceability.md` for the evaluation chapter.

**Coverage gates in CI:** ledger posting rules and business rules **100%**, overall **≥ 70%** (NFR-MNT-02). A PR that lowers coverage fails.

Tests never call real AI keys, Cloudinary, B2 or Brevo; they use fakes from `backend/tests/fakes/`. This keeps CI free and fast.

---

## 9. Build order

Build in vertical slices: each piece is usable end-to-end before the next starts.

1. **Foundation**: buy the domain and move its DNS to Cloudflare, create the free accounts, deploy a "hello world" backend to Koyeb and dashboard to Pages on **day one**; then CI, `core/` (config, db, tenancy + RLS, auth, permissions, events, storage), design tokens, dashboard shell.
2. **Store creation**: one-step create-your-store flow, dashboard home, seeded demo stores.
3. **Commerce**: catalog → cart → checkout (COD first, then Kashier test mode) → orders → notifications.
4. **Storefront + SEO**: SSR pages, bilingual URLs, sitemaps, JSON-LD, feeds, Cloudflare caching.
5. **Builder**: sections, themes, media, versions.
6. **Ledger + analytics**: posting rules on events, statements, KPIs, demand view.
7. **Adorna importer**: import, shadow-run data ready.
8. **AI team**: function registry over finished modules, question understanding, agents, proactive engine, FP&A, marketing intelligence.

Steps 1 to 7 make up the non-AI core sprint. The AI team can start step 8 early against stub functions (section 4.3).

---

## 10. SRS changes caused by free cloud hosting

The SRS was written for two laptops plus a paid Hostinger VPS. Moving to free cloud only changes these parts. Update the SRS and tell the supervisor, so the defense matches what we built.

| SRS part | Was | Now |
|---|---|---|
| §2.3 Operating environment, §3 architecture, §7.5 hardware | Laptop A (Ollama) + Laptop B (Docker) → Hostinger VPS | Koyeb + Neon + Cloudflare + free AI keys, in all stages |
| §2.5 Constraints | "Only planned cost is a Hostinger VPS" | No VPS. Costs are only the domain and the existing Hostinger web plan (landing, email, cron) |
| §2.3 Hosting note | Hostinger shared hosting serves static files only | Same conclusion: Hostinger hosts the landing page, mailboxes and cron; the app runs on free cloud services |
| §7.4 External services | Ollama local models, Fin-R1 7B | Cloud models only; FP&A commentary uses a free cloud model under the same number guard |
| FR-ONB-02 | `storename.minivora.app` | `storename.<domain>` via Cloudflare wildcard + Worker; path-based addresses only until the domain is live |
| NFR-PER-01, NFR-PER-06 | 500 ms TTFB, 5 s AI answers on the local AI server | Measured with edge caching and keep-alive; cold-start delays are a documented limitation |
| NFR-PRV-03 | Private data could stay on Laptop A | All AI calls are cloud calls, so masking is mandatory on every call |
| NFR-AIQ-04 | Compare local vs cloud models | Compare the free cloud providers against each other |
| NFR-REL-01 | Nightly backup on Laptop B's disk | Nightly `pg_dump` to Backblaze B2 + Neon's 6-hour restore window |
| NFR-SEC-09 | AI server port reachable only from the app server | Not applicable (no AI server); API keys stay server-side only |
