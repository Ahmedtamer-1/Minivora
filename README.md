# Minivora
 
**An AI-native, multi-store e-commerce engine for small Egyptian sellers.**
A seller describes the business in Arabic or English, an AI team builds the store, the store sells, and the owner runs it with finance-grade answers backed by a double-entry ledger.
 
> The full requirements live in [`docs/SRS.docx`](docs/SRS.docx). Every requirement has an ID (`FR-CAT-01`, `NFR-SEC-05`, …). Reference those IDs in branches, commits, PRs and tests so we can trace every result back to the SRS at the defense.
 
---
 
## Table of contents
 
1. [Architecture at a glance](#1-architecture-at-a-glance)
2. [Repository structure](#2-repository-structure)
3. [How the pieces connect](#3-how-the-pieces-connect)
4. [Who works where](#4-who-works-where)
5. [Getting started](#5-getting-started)
6. [Team conventions](#6-team-conventions)
7. [Testing and requirement traceability](#7-testing-and-requirement-traceability)
8. [Build order](#8-build-order)
---
 
## 1. Architecture at a glance
 
| Part | Tech | Runs on (dev) | Who uses it |
|---|---|---|---|
| **Backend API** | FastAPI, Pydantic v2, SQLAlchemy 2, Alembic | Laptop B (Docker) | Dashboard, AI team, webhooks |
| **Storefront** | Same FastAPI app, server-rendered with Jinja2 | Laptop B | Shoppers, search engines |
| **Dashboard** | React + TypeScript + Vite | Laptop B (built static files) | Owners, staff, platform admins |
| **Database** | PostgreSQL 16 + pgvector, Row-Level Security | Laptop B | Backend only |
| **Scheduler** | Background worker (same image as backend) | Laptop B | Proactive checks, nightly jobs |
| **AI server** | Ollama behind LiteLLM, cloud keys as fallback | Laptop A (RTX 4070) | Backend's `ai/` package only |
 
Four rules shape the whole codebase:
 
1. **Multi-tenant.** Every table except `stores` and `users` has a `store_id`, and RLS makes cross-store reads impossible (NFR-SEC-05).
2. **Money is integer piasters.** 1 EGP = 100 piasters, everywhere: DB, API and TypeScript. Never floats.
3. **AI never touches the database.** Agents call the same permission-checked service functions the dashboard uses (FR-AI-06). LLMs never produce financial figures; code does (FR-AI-03, FR-FPA-06).
4. **Every money event posts to the ledger.** Orders, refunds, fees and ad spend create balanced journal entries (FR-LED-02, FR-LED-03).
---
 
## 2. Repository structure
 
This is a **monorepo**: one Git repo, one CI pipeline, one `docker compose up`.
 
```
minivora/
├── README.md
├── docker-compose.yml            # Laptop B: db, backend, worker, dashboard, proxy
├── docker-compose.ai.yml         # Laptop A: Ollama + LiteLLM
├── .env.example                  # every variable documented; real .env is NEVER committed
├── Makefile                      # make dev | test | lint | migrate | seed | api-client | tokens
│
├── .github/
│   ├── workflows/ci.yml          # ruff, mypy, pytest, eslint, tsc, vitest, playwright
│   ├── CODEOWNERS                # maps folders to owners (see section 4)
│   ├── PULL_REQUEST_TEMPLATE.md
│   └── dependabot.yml            # NFR-SEC-11
│
├── backend/                      # Python, FastAPI — API + storefront + worker
│   ├── pyproject.toml            # ruff, mypy, pytest config
│   ├── Dockerfile
│   ├── alembic/versions/         # ALL schema changes go here (NFR-MNT-03)
│   ├── app/
│   │   ├── main.py               # creates the app, mounts api/v1 + storefront
│   │   ├── core/                 # shared plumbing — changes need 2 reviewers
│   │   │   ├── config.py         # pydantic-settings, reads .env
│   │   │   ├── db.py             # engine, session, sets app.store_id for RLS
│   │   │   ├── tenancy.py        # resolves current store from subdomain / JWT
│   │   │   ├── security.py       # Argon2id, JWT, CSRF, rate limits
│   │   │   ├── permissions.py    # role matrix from SRS §7.2
│   │   │   ├── events.py         # in-process domain event bus
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
│   │   │   │   └── gateways/     # kashier.py, paymob.py, instapay.py, cod.py, fawry.py
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
│   │   │   #   service.py      business logic — the ONLY public entry point
│   │   │   #   router.py       /api/v1/<module> endpoints (thin: validate → service)
│   │   │   #   events.py       events this module emits / listens to
│   │   │   #   ai_functions.py functions exposed to the AI team (optional)
│   │   │   #   tests/
│   │   │
│   │   ├── storefront/           # server-rendered shop (FR-SEO-09)
│   │   │   ├── routes.py         # /ar/... and /en/... pages
│   │   │   ├── templates/        # Jinja2: layouts, pages, partials
│   │   │   │   └── sections/     # one template per builder section type (FR-BLD-01)
│   │   │   └── static/           # < 100 KB JS per page (NFR-PER-03)
│   │   │
│   │   ├── ai/                   # AI management team
│   │   │   ├── llm/              # LiteLLM client, key rotation, local→cloud fallback, PII masking
│   │   │   ├── functions/        # registry: collects every module's ai_functions.py + permission check
│   │   │   ├── understanding/    # question → one function + params (FR-AI-02)
│   │   │   ├── agents/           # ceo/, cfo/, cmo/, cro/, coo/, cxo/, cto/, chro/, risk/
│   │   │   ├── coordinator/      # conflict rules between agents (FR-AI-08)
│   │   │   ├── proactive/        # checks, insight cards, "Today" feed, morning brief (FR-PRO)
│   │   │   ├── guards/           # number guard (FR-FPA-06)
│   │   │   └── eval/             # evaluation set + runner (NFR-AIQ)
│   │   │
│   │   ├── jobs/                 # scheduler: hourly/daily/weekly checks, memos, reconcile, backups
│   │   └── api/v1/router.py      # mounts every module's router under /api/v1
│   │
│   ├── importers/adorna/         # one-way MySQL → Minivora importer, nightly (FR-ONB-09, §2.7)
│   ├── seeds/                    # 5 demo stores, 200 products each (§2.4)
│   └── tests/                    # cross-module: RLS isolation, ledger balance, flows
│
├── dashboard/                    # TypeScript, React — owner/staff/admin app
│   ├── package.json
│   ├── src/
│   │   ├── app/                  # router, providers, layout shell, auth guard
│   │   ├── features/             # mirrors SRS §7.1 dashboard areas
│   │   │   ├── onboarding/  home/  ai-chat/  approvals/  products/  orders/
│   │   │   ├── customers/  builder/  marketing/  seo/  finance/  settings/
│   │   │   └── platform-admin/
│   │   │       # each feature: pages/, components/, hooks/ (data fetching), *.test.tsx
│   │   ├── components/ui/        # shared design-system components (Button, Card, Input, Badge, GlassPanel…)
│   │   ├── lib/
│   │   │   ├── api/schema.ts     # GENERATED from OpenAPI — do not edit by hand
│   │   │   ├── api/client.ts     # typed fetch wrapper, JWT refresh
│   │   │   ├── money.ts          # piasters ↔ display
│   │   │   └── i18n.ts           # ar/en, RTL switch
│   │   ├── locales/ar.json, en.json
│   │   └── styles/
│   │       ├── tokens.css        # GENERATED from design/tokens — do not edit by hand
│   │       └── tokens.ts         # GENERATED typed token names
│   └── tests/
│
├── design/
│   ├── tokens/                   # tokens.json exported from Figma (SCRUM-102)
│   ├── build-tokens.mjs          # tokens.json → dashboard/src/styles/tokens.{css,ts}
│   └── README.md                 # Figma file link + how to re-export
│
├── ai-server/                    # Laptop A setup only (no app code)
│   ├── litellm.config.yaml       # model routing + cloud fallback keys (from env)
│   └── pull-models.sh            # Qwen, Fin-R1, Gemma, bge-m3
│
├── e2e/                          # Playwright: shopper + owner journeys (SRS §9.3)
├── load/                         # k6 scripts (NFR-PER-01, NFR-SCL-03)
├── infra/
│   ├── caddy/Caddyfile           # HTTPS, HSTS, routing (see section 3)
│   ├── cloudflared/              # dev tunnel config
│   ├── backup/                   # nightly pg_dump + weekly restore test (NFR-REL-01)
│   └── deploy/                   # Hostinger VPS / AWS notes and scripts
│
└── docs/
    ├── SRS.docx
    ├── architecture.md
    ├── traceability.md           # requirement ID → code → test (generated)
    └── adr/                      # architecture decision records, one file per decision
```
 
---
 
## 3. How the pieces connect
 
Each connection below is a **contract**. If you respect the contract, your part plugs in at the end without anyone else changing their code.
 
### 3.1 Backend ↔ Dashboard: the OpenAPI schema
 
FastAPI publishes `/api/v1/openapi.json` automatically from the Pydantic schemas. The dashboard **generates** its types from it:
 
```bash
make api-client    # backend running → regenerates dashboard/src/lib/api/schema.ts
```
 
- Backend devs change `schemas.py` → run `make api-client` → commit the regenerated file in the same PR.
- Frontend devs never hand-write API types. If a field is missing, ask the module owner to add it to the schema.
- CI fails if `schema.ts` is out of date with the backend.
- Auth: short-lived JWT access token + refresh token (SRS §7.3).
### 3.2 Module ↔ Module: services and events, never tables
 
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
 
This is how the ledger "connects" to everything without every module knowing about accounting.
 
### 3.3 AI team ↔ Everything else: the function registry
 
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
### 3.4 Storefront ↔ Modules: in-process calls
 
The storefront lives inside the backend, so its routes call module services directly (no HTTP round-trip). Section templates in `storefront/templates/sections/` must match section types defined in `modules/builder/` one-to-one.
 
### 3.5 Backend ↔ AI server: one environment variable
 
```env
LLM_BASE_URL=http://<laptop-a-ip>:4000      # LiteLLM on Laptop A
LLM_FALLBACK_KEYS=groq:...,gemini:...,openrouter:...
```
 
If Laptop A is off, `ai/llm/` falls back to cloud keys automatically (FR-AI-11). Personal data is masked before any cloud call (NFR-PRV-03).
 
### 3.6 Figma ↔ Dashboard: design tokens
 
```bash
make tokens   # design/tokens/tokens.json → dashboard/src/styles/tokens.{css,ts}
```
 
Use `var(--mv-*)` variables or the typed `tokens.ts` names. Never hardcode colors, spacing or radii in components.
 
### 3.7 The whole thing: one proxy
 
`infra/caddy/Caddyfile` routes everything on one machine:
 
| Request | Goes to |
|---|---|
| `app.minivora.app/*` | dashboard static build |
| `app.minivora.app/api/*`, `/webhooks/*` | backend API |
| `<store>.minivora.app/*`, custom domains | backend storefront (store resolved by host) |
 
`docker compose up` starts: `db`, `backend`, `worker`, `dashboard`, `caddy`. The same images move to the Hostinger VPS or AWS unchanged (SRS §2.3).
 
---
 
## 4. Who works where
 
Fill in names and mirror this table in `.github/CODEOWNERS`. One owner per area; anyone may contribute, but the owner reviews.
 
| Area | Folders | SRS sections | Owner |
|---|---|---|---|
| Platform core, auth, tenancy, CI | `backend/app/core/`, `modules/platform/`, `modules/accounts/`, `.github/`, `infra/` | FR-ACC, FR-ONB, NFR-SEC | _TBD_ |
| Catalog, cart, checkout, payments | `modules/catalog/`, `cart/`, `checkout/` | FR-CAT, FR-CRT, FR-PAY | _TBD_ |
| Orders, shipping, notifications | `modules/orders/`, `notifications/` | FR-ORD, FR-NTF | _TBD_ |
| Storefront + SEO | `backend/app/storefront/`, `modules/seo/` | FR-SEO, NFR-PER | _TBD_ |
| Builder, themes, media | `modules/builder/`, `dashboard/src/features/builder/` | FR-BLD | _TBD_ |
| Ledger, FP&A, analytics | `modules/ledger/`, `fpa/`, `analytics/` | FR-LED, FR-FPA, FR-ANA | _TBD_ |
| Marketing + marketing intelligence | `modules/marketing/`, `marketing_intel/` | FR-MKT, FR-MI | _TBD_ |
| AI team | `backend/app/ai/`, `ai-server/` | FR-AI, FR-PRO, NFR-AIQ | _TBD_ |
| Dashboard shell + design system | `dashboard/src/app/`, `components/ui/`, `design/` | §7.1, NFR-USE | _TBD_ |
| Adorna importer + pilot | `backend/importers/adorna/` | §2.7, FR-ONB-09 | _TBD_ |
 
Shared files (`core/`, `alembic/`, `docker-compose.yml`, `.env.example`) need **two approvals**.
 
---
 
## 5. Getting started
 
**Prerequisites:** Docker Desktop, Git, Node 20+, Python 3.12+ (for local tooling). Laptop A additionally needs the NVIDIA driver + Ollama.
 
```bash
git clone <repo-url> minivora && cd minivora
cp .env.example .env              # fill in values from the team vault — never commit .env
make dev                          # docker compose up --build
make migrate                      # alembic upgrade head
make seed                         # 5 demo stores
```
 
| URL | What |
|---|---|
| http://localhost/api/v1/docs | Swagger (OpenAPI) |
| http://app.localhost | Dashboard |
| http://demo.localhost | A seeded storefront |
 
On **Laptop A**: `docker compose -f docker-compose.ai.yml up`, then `./ai-server/pull-models.sh`. Its port must accept connections from Laptop B only (NFR-SEC-09).
 
---
 
## 6. Team conventions
 
**Branches**: `main` is protected (PR + green CI + 1 review). Branch from `main` using the Jira key:
 
```
feat/SCRUM-102-design-tokens
fix/SCRUM-131-cod-fee-rounding
```
 
**Commits**: start with the Jira key so Jira's Development panel links them: `SCRUM-102 add glass tokens to tokens.json`.
 
**Pull requests**: one Jira issue per PR, list the SRS requirement IDs it covers, include screenshots for UI changes.
 
**Python** (backend): `ruff` (lint + format) and `mypy --strict` on `app/`. Every request/response body is a Pydantic v2 model in `schemas.py`; no raw `dict` responses.
 
**TypeScript** (dashboard): `strict: true`, ESLint + Prettier, no `any`. API types come only from the generated `schema.ts`.
 
**Database**: every new table has `store_id` + an RLS policy + a line in the RLS isolation test. Money columns are `BIGINT` piasters. Schema changes only via Alembic.
 
**Secrets**: never in code, Git or zip archives (NFR-SEC-06). Add new variables to `.env.example` with a comment.
 
**Repo hygiene**: no duplicate or "copy" files (`auth (1).php`-style), no dead code (NFR-MNT-04).
 
**Language**: all user-facing text goes through i18n (`locales/ar.json`, `en.json`, or Jinja `_()`). Layouts must mirror correctly in RTL.
 
---
 
## 7. Testing and requirement traceability
 
| Layer | Tool | Lives in |
|---|---|---|
| Unit / module | pytest | `backend/app/modules/<x>/tests/` |
| Cross-module (RLS, ledger, flows) | pytest | `backend/tests/` |
| Dashboard components | Vitest + Testing Library | next to the component |
| Browser journeys | Playwright | `e2e/` |
| Load | k6 | `load/` |
| AI quality | eval runner | `backend/app/ai/eval/` |
 
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
 
---
 
## 8. Build order
 
Build in vertical slices: each piece is usable end-to-end before the next starts.
 
1. **Foundation**: repo, CI, Docker, `core/` (config, db, tenancy + RLS, auth, permissions, events), design tokens, dashboard shell.
2. **Store creation**: one-step create-your-store flow, dashboard home, seeded demo stores.
3. **Commerce**: catalog → cart → checkout (COD first, then Kashier test mode) → orders → notifications.
4. **Storefront + SEO**: SSR pages, bilingual URLs, sitemaps, JSON-LD, feeds.
5. **Builder**: sections, themes, media, versions.
6. **Ledger + analytics**: posting rules on events, statements, KPIs, demand view.
7. **Adorna importer**: import, shadow-run data ready.
8. **AI team**: function registry over finished modules, question understanding, agents, proactive engine, FP&A, marketing intelligence.
Steps 1 to 7 make up the non-AI core sprint. The AI team can start step 8 early against stub functions (section 3.3).
 
