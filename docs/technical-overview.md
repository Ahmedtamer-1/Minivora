# Minivora — Technical Overview

Oct 8, 2026 · @bassam islam

## At a glance

Minivora is one Laravel web app that hosts many stores, Shopify-style, and starts on the team's existing Hostinger plan. Sellers sign up and get theirstore.<domain> instantly; nobody gets a separate server. Full requirements are in the SRS v1.2.

| Question | Answer |
| --- | --- |
| What is it? | A multi-store e-commerce platform with an AI management team and an accounting ledger |
| One app or many? | One app, one database; each store is a row in the stores table |
| Where does it run now? | Hostinger web hosting (shared), the plan Adorna EG already uses |
| Where does it go later? | A VPS (Hostinger or DigitalOcean), then AWS or Google Cloud, with the same code |
| Languages | PHP (Laravel) on the server, TypeScript + React for the dashboard |
| Cost today | 0 EGP extra: existing Hostinger plan, Namecheap domain, free AI keys |
| First store | Adorna EG, imported for the pilot |

## The stack

Everything runs on plain PHP + MySQL hosting, so deploying is as easy as Adorna's: no Docker, no root access, no Node or Python on the server.

| Layer | Technology | Why |
| --- | --- | --- |
| Backend | Laravel, current version (PHP 8.3+) | Runs on any Hostinger or cPanel plan; Adorna's PHP logic ports over directly |
| Database | MySQL 8 (or MariaDB) | Included in every hosting plan; Laravel migrations rebuild it anywhere |
| Storefront | Laravel Blade (server-rendered HTML) | Fast pages and full SEO; works without JavaScript |
| Dashboard | React + TypeScript via Inertia.js | Modern UI with typed code, no separate API server to host |
| UI styling | Design tokens from Figma (SCRUM-102) | One source of colors, spacing and glass styles |
| Background work | Laravel scheduler + database queue, run by cron every minute | Shared hosting forbids always-running processes |
| AI | Gemini, Groq, OpenRouter free APIs over HTTPS | Free; keys rotate when one hits its limit |
| Store isolation | store_id on every table + automatic query scoping | Stores can never see each other's data |
| Tests and checks | Pest, PHPStan, Pint, ESLint, TypeScript compiler, Playwright | Run on every push in GitHub Actions |

## Hosting stages

We start on the Hostinger plan we already pay for and move up only when traffic forces it. Moving is a weekend job: deploy the same code, import the database, point DNS to the new server.

| Stage | Where | Move here when | Extra cost | What changes |
| --- | --- | --- | --- | --- |
| 1. Build + Adorna pilot (now) | Hostinger web hosting (shared) | Now | None | Nothing, it's the starting point |
| 2. First real sellers | Hostinger VPS or DigitalOcean | hPanel shows CPU/memory limits hit, pages slow at busy times, cron jobs time out, or several stores have real traffic | From about $6.49/month (intro); students may get DigitalOcean credit via the GitHub Student Developer Pack | Add Redis for queue and cache; queue runs as a real worker |
| 3. Large scale | AWS or Google Cloud | Many active stores; one server isn't enough | Pay as you go | Several app servers behind a load balancer, managed MySQL, S3 storage for media |

Rules that keep moving painless: settings live only in .env, files go through Laravel's storage layer, background work goes through Laravel's queue, and every database change is a migration.

## How stores work

Every store lives inside the same app, like Shopify: creating a store adds a database row, not a server or an hPanel website.

- A seller fills in "Create your store" (store name, email, password).
- The app saves a row in stores with the name adorna and the owner's account.
- A visitor opens adorna.<domain>; Cloudflare's wildcard record sends it to our one app on Hostinger.
- The app reads the address, finds the adorna row and shows only that store's data.
We build it multi-store from day one, even while Adorna is the only store. Converting a single-store app later is the expensive path.

One-time setup (domain at Namecheap, nameservers pointed to Cloudflare):

| Where | Setting | Value |
| --- | --- | --- |
| Cloudflare DNS | <domain> and www | Hostinger server IP, proxied |
| Cloudflare DNS | * (wildcard) | Hostinger server IP, proxied, so every store subdomain works |
| Cloudflare DNS | MX + mail records | Hostinger mail servers, not proxied |
| Cloudflare SSL | Mode | Full, so all subdomains get HTTPS automatically |
| hPanel | Website | Add <domain>; document root = the app's public folder |
| hPanel | Subdomain * | Same folder as the main site (cPanel: Domains, create *.<domain>) |
| App .env | APP_DOMAIN | <domain> |

Reserved names that can never be store names: app, api, www, mail, admin, status.

Sellers' own domains (later): Cloudflare's custom hostnames let a seller point theirshop.com at Minivora; the free tier covers the first 100 domains. The app maps that domain to their store.

## Services and accounts

These services run the whole project; all keys live in one shared Bitwarden vault, never in chat or Git.

| Service | Used for | Plan |
| --- | --- | --- |
| Hostinger web hosting | App, MySQL, cron, team mailboxes, SMTP email | Existing plan |
| Namecheap | The Minivora domain | Existing |
| Cloudflare | DNS, HTTPS for all subdomains, caching | Free |
| Google AI Studio (Gemini), Groq, OpenRouter | All AI agents | Free keys, several per provider |
| Kashier, Paymob | Card and wallet payments | Test mode |
| GitHub | Code, CI, building the dashboard | Free |
| Backblaze B2 | Off-site copy of nightly database backups | Free 10 GB |
| UptimeRobot | Uptime alerts | Free |
| Brevo | Backup email sender if Hostinger's sending limit is reached | Free, only if needed |

## Code layout

One Laravel repo, split into one folder per business area; every area has the same files, so the tenth module looks like the first. Fill in owners before the first sprint.

| Folder | What lives there | SRS | Owner |
| --- | --- | --- | --- |
| app/Core | Store scoping, roles and permissions, money (piasters), audit log | FR-ACC, NFR-SEC | TBD |
| app/Modules/Catalog, Cart, Checkout | Products, variants, search, cart, payments | FR-CAT, FR-CRT, FR-PAY | TBD |
| app/Modules/Orders, Notifications | Orders, shipping, returns, emails | FR-ORD, FR-NTF | TBD |
| app/Modules/Builder, Seo | Sections, themes, media, sitemaps, JSON-LD | FR-BLD, FR-SEO | TBD |
| app/Modules/Ledger, Fpa, Analytics | Journal entries, reports, budgets, KPIs | FR-LED, FR-FPA, FR-ANA | TBD |
| app/Modules/Marketing | Pixels, discounts, Meta campaigns | FR-MKT, FR-MI | TBD |
| app/Ai | AI agents, function registry, number guard, proactive engine | FR-AI, FR-PRO | TBD |
| resources/views/storefront | Blade templates for shopper pages and builder sections | FR-SEO-09 | TBD |
| resources/js | React + TypeScript dashboard, design tokens | §7.1 | TBD |
| app/Importers/Adorna | Adorna EG import for the pilot | §2.7 | TBD |

Each module folder holds: Models, Http (controllers + form validation), Services (business logic, the only entry point other modules may call), Events, Jobs, AiFunctions, Tests. Modules never read each other's tables; they call each other's services or react to events such as OrderPaid.

## Deploying to Hostinger

A deploy is a Git pull plus three commands; the same steps work on any cPanel host.

- GitHub Actions runs the tests and builds the React dashboard (npm run build) on every push to main, so the server never needs Node.
- In hPanel, connect the repo under Advanced → Git and set the website's document root to the app's public folder.
- Create the MySQL database in hPanel and copy .env.example to .env with its name, user and password, plus the AI keys.
- Over SSH, run composer install --no-dev, then php artisan migrate --force, then php artisan optimize.
- Add one cron job in hPanel, every minute:
cd ~/minivora && php artisan schedule:run >> /dev/null 2>&1

That single cron line runs everything scheduled: the AI team's hourly and daily checks, the 09:00 morning brief, emails, the nightly Adorna import and backups. Queued jobs run in short batches inside it, because shared hosting stops long-running processes.

## Team rules

Eight rules cover most mistakes; CI enforces the ones it can.

| Rule | In practice |
| --- | --- |
| Branch per Jira issue | feat/SCRUM-102-design-tokens; main is protected and needs green CI + 1 review |
| Commit messages start with the Jira key | SCRUM-102 add glass tokens links the commit in Jira |
| Every table has store_id | Plus a line in the store-isolation test that proves no cross-store reads |
| Money is integer piasters | 1 EGP = 100 piasters, in MySQL, PHP and TypeScript; never floats |
| AI never touches the database | Agents call module services through the AI function registry, with permission checks |
| No secrets in Git | Keys go in .env and Bitwarden; .env.example lists every variable |
| Tag tests with requirement IDs | test('FR-PAY-02 rejects non-Egyptian numbers'), so the traceability table builds itself |
| All text through translations | Arabic and English files; layouts must mirror in right-to-left |

## What changed from the earlier plan

The SRS v1.2 records these changes; the supervisor should approve them before the defense.

| Area | Earlier plan | Now |
| --- | --- | --- |
| Backend | FastAPI (Python) | Laravel (PHP) |
| Database | PostgreSQL + pgvector | MySQL |
| Store isolation | PostgreSQL Row-Level Security | Automatic store scoping in the app + isolation test |
| Hosting | Two laptops, then a Hostinger VPS | Existing Hostinger web hosting, then VPS, then AWS/Google Cloud |
| AI | Local models on a laptop GPU + cloud fallback | Free cloud APIs only (Gemini, Groq, OpenRouter) |
| Dashboard | React | React + TypeScript via Inertia.js |
| Storefront | Jinja2 templates | Blade templates |
| Background jobs | Always-running scheduler | Cron every minute (stage 1), real workers from stage 2 |
| Typing standard | Pydantic v2 | Laravel form validation + TypeScript |
| Search by meaning (FR-CAT-06, Could) | pgvector | Deferred to stage 2 |

The SCRUM-102 acceptance criterion that names "Pydantic v2" should be updated in Jira to "Laravel validation / TypeScript".
