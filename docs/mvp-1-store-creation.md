# MVP 1: Platform foundation and store creation

Scope of the first working slice of Minivora: build-order steps 1 and 2 from the README
(foundation + store creation). No AI calls yet, but every piece is shaped so the AI team
plugs in later without rewrites.

## In scope

| Area | What ships | SRS |
|---|---|---|
| Tenancy | `CurrentStore`, `StoreScope`, `BelongsToStore`, host and session resolvers, reserved names | NFR-SEC-05, FR-ONB-02 |
| Money | `Piasters` value object + EGP formatter (Western digits) | NFR-USE-05 |
| Roles | `Role` and `Permission` enums, permission matrix copied from SRS §7.2 | FR-ACC-03 |
| Activity log | Append-only, hash-chained log with actor type `user` / `agent` / `system`; `activity-log:verify` command | FR-ACC-05, NFR-SEC-10 |
| Create your store | One form: store name, address, email, password. Creates user + store + owner membership in one transaction and lands in the dashboard | FR-ONB-01, FR-ONB-03, FR-ACC-07 |
| Address check | Live availability check as the owner types, with suggestions; reserved names never available | FR-ONB-02 |
| Onboarding questions | Up to three skippable questions (what you sell, where you are, store language), saved as the store profile | FR-ONB-04 |
| Opening soon | New stores are password-protected until published; owner previews via signed link | FR-ONB-06 |
| Email verification | Sent in the background; required to publish, not to build | FR-ONB-07 |
| Store switcher | Create another store with the same account; switch between stores | FR-ONB-08 |
| Setup checklist | Add products, connect payment, set shipping, publish; computed from store state | FR-ONB-10 |
| Sign in | Email + password, lock for 15 minutes after 5 failures per account + IP | NFR-SEC-02 |
| Members | Owner invites by email with a role, changes role, revokes access; invite accept flow | FR-ACC-04 |
| Platform admin | List, suspend and restore stores, with basic usage numbers | FR-ACC-08 |
| Storefront shell | `{store}.<domain>`, `/ar` and `/en`, `lang`/`dir` set, opening-soon / suspended / published states | FR-CAT-15, FR-SEO-22 |
| Design system | `design/tokens/tokens.json` (Light + Dark from Figma) → `resources/css/tokens.css`; dashboard UI kit | §7.1 |
| CI | Pint, Pest, tsc, Vite build on every push | NFR-MNT-01 |

## Built for the AI team (no LLM calls yet)

- **Function registry** (`app/Ai/Registry`): modules expose `#[AiFunction]` methods in `AiFunctions/`
  classes, each declaring its `Permission` and whether it needs owner approval (FR-AI-06, FR-AI-07).
  The registry checks the asking member's role before calling. Platform ships two read-only
  functions: `store.profile` and `store.setup_checklist`.
- **Store profile**: onboarding answers are stored as structured data and fire
  `StoreProfileUpdated`. The Store Builder agent (FR-ONB-05, FR-BLD-05) will listen to this event.
- **Activity log actors**: every write records who did it, user, agent (with agent name) or system,
  so FR-AI-10 ("which agent acted") needs no schema change.
- **Services only**: controllers stay thin; all business logic is in `Services/`, which is what
  both the dashboard and future AI functions call.

## Out of scope for MVP 1

Catalog, cart, checkout, orders, builder, SEO, marketing, ledger, AI agents, Google sign-in, 2FA,
custom domains and the Adorna importer. Their placeholder files stay as they are.

## Running locally

See README §6. For a quick local run without MySQL, set `DB_CONNECTION=sqlite` in `.env`.

## How the next modules plug in

| To add… | Do this |
|---|---|
| A store-owned table | Migration with `store_id`; model uses `BelongsToStore`. `tests/Isolation` finds it automatically and fails if it isn't scoped. |
| An AI-callable function | A class in `Modules/<X>/AiFunctions/` with `#[AiFunction(name, description, Permission, needsApproval)]` methods; list the class in `config/minivora.php` → `ai.functions`. |
| A setup-checklist step | Implement `Platform/Setup/SetupStep` and swap it for the matching `PendingModuleStep` in `SetupChecklistService`. |
| A logged action | `ActivityLogger::log('area.action', $model, [...])`; agents pass `Actor::agent('cfo', $userId)`. |
| A dashboard page | Inertia page in `resources/js/pages/`, strings in `lang/ar.json` + `lang/en.json`, colours only from tokens. |
| A route that needs a permission | `->middleware('store.can:products.manage')` (values from `App\Core\Permissions\Permission`). |

## Decisions and deviations

- **Laravel 12 instead of 11.** Laravel 11 is out of security support and `composer audit` reported 5
  unpatched advisories; 12 is a drop-in upgrade with none.
- **PHPStan not in CI yet.** `larastan` could not be installed through this environment's network
  proxy; add it back to `require-dev` and CI from a normal machine.
- **Platform host.** The landing page, sign-in and dashboard answer on both `<domain>` and
  `app.<domain>`; every other subdomain is a store.
- **Owner preview** uses a 5-minute signed link that unlocks the storefront in that browser, so no
  cookies are shared between the dashboard and store subdomains.
- **Permissions** are enforced by the `store.can:` route middleware and the AI registry, both reading
  the one `PermissionMatrix`; the empty per-module policy placeholders were removed.
- **Store address can't be changed** after creation yet (it would need redirects, FR-SEO-12).
- **Not built yet from these SRS areas:** Google sign-in (FR-ACC-01, FR-ONB-01 option), 2FA
  (FR-ACC-06), custom domains, and the importers (FR-ONB-09).

## Verification

- `vendor/bin/pest`: 82 tests, named after the requirement they prove (FR-ONB-01…10, FR-ACC-03/04/05/07/08,
  NFR-SEC-01/02/05/10, FR-AI-06, NFR-USE-05).
- `npm run test:e2e` (app running and seeded): owner creates a store and reaches its dashboard, desktop and mobile.
- `vendor/bin/pint --test`, `npm run typecheck`, `npm run build`.
