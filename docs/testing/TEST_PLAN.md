# Minivora — Software Test Plan

**Project:** Minivora Multi-Store E-Commerce Platform with AI Management Team & Double-Entry Ledger  
**Reference Specification:** `Minivora_SRS_v1.8.docx` (241 Requirements: 175 FR, 66 NFR; Verification Checks `V-01`–`V-18`)  
**Standard Alignment:** IEEE 829 / ISO/IEC/IEEE 29119 & ISO/IEC 25010:2023  
**Repository:** `Ahmedtamer-1/Minivora` (Laravel 12 / PHP 8.3+, MySQL 8 / SQLite, Blade Storefront, React + TypeScript via Inertia.js)

---

## 1. Purpose & Scope

### 1.1 Purpose
This Test Plan defines the strategy, scope, test types, environments, entry/exit criteria, tooling, and deliverables for verifying and validating **Minivora** against **SRS v1.8**. It covers both **functional verification** (test cases across all 13 business modules + AI team) and **non-functional validation** (load, stress, spike, soak, security, multi-tenant isolation, usability, accessibility, and reliability testing).

### 1.2 In-Scope Modules & Quality Characteristics
| Area | Modules / Components | SRS Coverage |
|---|---|---|
| **Core & Tenancy** | `app/Core/` (Tenancy, Piasters, Permissions, Hash-Chained Audit Log) | `FR-ONB`, `FR-ACC`, `NFR-SEC-01..17` |
| **Commerce Engine** | `Catalog`, `Cart`, `Checkout`, `Orders`, `Notifications` | `FR-CAT`, `FR-CRT`, `FR-PAY`, `FR-ORD`, `FR-NTF` |
| **Storefront, Builder & SEO** | Blade storefront, `Builder`, `Seo`, `Marketing`, `Analytics` | `FR-BLD`, `FR-SEO`, `FR-MKT`, `FR-MI`, `FR-ANA` |
| **Finance & Accounting** | `Ledger` (double-entry), `Fpa` (budgets, scenarios, forecasts) | `FR-LED`, `FR-FPA`, `NFR-SEC-12`, `NFR-PRV-06` |
| **AI Management Team** | `app/Ai/` (LLM Router, Registry, Number Guard, Proactive Engine, Agents) | `FR-AI`, `FR-PRO`, `NFR-AIQ-01..10`, `NFR-PRV-03`, `NFR-PRV-05` |
| **Pilot Importer** | `app/Importers/Adorna/` | `FR-ONB-09`, SRS §2.7–2.8 |
| **Non-Functional** | Performance, Scalability, Security, Privacy, Reliability, Usability | `NFR-PER`, `NFR-SCL`, `NFR-SEC`, `NFR-PRV`, `NFR-REL`, `NFR-USE` |

### 1.3 Out of Scope (Deferred per SRS §10.1)
- Semantic vector search (`FR-CAT-06`, Could — deferred to Stage 2).
- Real live payment gateway settlements (only test mode for Kashier/Paymob/Fawry plus COD and InstaPay receipt verification; `0` real financial transactions per `V-04` / `NFR-OPS-03`).
- Multi-server horizontal autoscaling (Stage 3 AWS/GCP architecture).

---

## 2. Test Strategy & Test Levels

| Test Type | Objective | Tooling | Location |
|---|---|---|---|
| **Unit Testing** | Validate pure domain rules: integer piasters math, balanced journal entries, RBAC permission matrix, subdomain rules, PII masking, AI Number Guard. | Pest PHP | `tests/Unit/` |
| **Feature / Integration Testing** | Verify HTTP controllers, services, events (`OrderPaid`, `OrderShipped`, `OrderReturned`), DB transactions, rate limits, and webhooks. | Pest PHP | `tests/Feature/` |
| **Multi-Tenant Isolation Testing** | Prove zero cross-store data leakage across every model, route, cache key, and AI function (`NFR-SEC-05`, `NFR-SEC-17`). | Pest PHP | `tests/Isolation/StoreIsolationTest.php` |
| **End-to-End (E2E) & A11y Testing** | Verify real browser journeys (owner store creation, shopper COD checkout ≤ 8 fields, order tracking, keyboard-only checkout, axe-core WCAG checks). | Playwright + axe | `e2e/` |
| **Smoke Performance Testing** | Fast 1-VU sanity verification of all load-test endpoints before heavier runs. | k6 | `tests/load/smoke.js` |
| **Load Testing (`V-10`)** | Validate SRS `NFR-PER-01` (50 concurrent virtual shoppers for 5 min, TTFB p95 ≤ 800 ms) and `NFR-PER-04` (dashboard p95 ≤ 1000 ms). | k6 | `tests/load/load-storefront.js`, `tests/load/load-dashboard-and-api.js` |
| **Stress Testing** | Ramp VUs beyond Stage 1 limits (`50 → 250 VUs`) to locate breaking point, rate-limit behavior (`429`), error threshold, and recovery. | k6 | `tests/load/stress-storefront.js` |
| **Spike Testing** | Simulate flash-sale / influencer traffic surge (`10 → 150 VUs` in 10 s) to validate burst handling and stability. | k6 | `tests/load/spike-flash-sale.js` |
| **Soak / Endurance Testing** | Sustained load (`30 VUs` for 30+ min) across multiple stores to detect memory leaks, session bloat, and latency drift. | k6 | `tests/load/soak-endurance.js` |
| **AI Evaluation & Prompt Injection** | 50 owner questions (≥ 20 Arabic, 20 held-out) + 20 prompt-injection payloads (`V-09`, `NFR-SEC-15`). | `app/Ai/Eval` + Pest | `app/Ai/Eval/`, `tests/Feature/Ai/` |
| **Security & Privacy Testing** | OWASP Top 10, CSRF, webhook replay/timestamp (`NFR-SEC-14`), session timeouts (`NFR-SEC-13`), PII masker audit (`NFR-PRV-03`), immutable ledger (`NFR-SEC-12`). | Pest + Manual + Dependabot | `tests/Feature/Security/` |
| **Usability & SEO Testing** | 5 first-time owners (store + product ≤ 15 min, SUS ≥ 68), Lighthouse Mobile ≥ 80 (`LCP ≤ 2.5 s`, `CLS ≤ 0.1`), Lighthouse SEO ≥ 90, Rich Results. | Lighthouse + Usability Lab | Manual (`V-03`, `V-06`, `V-10`, `V-12`) |

---

## 3. Test Environment & Test Data

### 3.1 Environments
1. **Local Development / QA (SQLite or MySQL 8):**
   - PHP 8.3+, Laravel 12, Composer, Node.js 22+, Vite production build (`npm run build`).
   - Seeded via `php artisan migrate:fresh --seed` (`DemoStoresSeeder` + `FinanceDemoSeeder`).
   - Supports both subdomain mode (`http://demo.localhost:8000`) and path mode (`STOREFRONT_MODE=path` → `http://localhost:8000/s/demo`).
2. **Stage 1 Staging / Pilot (Hostinger Shared Hosting + Cloudflare Free):**
   - Used for off-peak `V-10` k6 load run (50 VUs × 5 min), Lighthouse mobile audits, HTTPS/HSTS header checks, and Backblaze B2 backup/restore rehearsals.

### 3.2 Seeded Accounts & Stores (Local Only)
| Account | Email | Password | Role / Scope |
|---|---|---|---|
| Platform Admin | `admin@minivora.test` | `password` | Platform-wide admin |
| Store 0 Owner | `owner0@minivora.test` | `password` | `demo` (Minivora Demo, EN, Live, 6 months finance data) |
| Store 1 Owner | `owner1@minivora.test` | `password` | `nile-crafts` (حرف النيل, AR, Live) |
| Store 2 Owner | `owner2@minivora.test` | `password` | `cairo-leather` (Cairo Leather Co., EN, Live) |
| Store 3 Owner | `owner3@minivora.test` | `password` | `sabah-coffee` (بن الصباح, AR, Unpublished/Draft) |
| Store 4 Owner | `owner4@minivora.test` | `password` | `alex-tech` (Alex Tech, EN, Unpublished/Draft) |

---

## 4. Entry & Exit Criteria

### 4.1 Entry Criteria
- Application builds cleanly (`composer install`, `npm run build`, `php artisan migrate:fresh --seed`).
- CI static checks pass (`pint --test`, `tsc --noEmit`).
- No real production payment or AI credentials are active in the test `.env` (`NFR-OPS-03`).

### 4.2 Exit Criteria (Release / Assignment Sign-Off)
- **100%** of Critical and High priority functional test cases pass.
- **100%** code coverage on Ledger posting rules and AI business rules; **≥ 70%** overall (`NFR-MNT-02`).
- **Zero** cross-store data leaks across all isolation tests (`NFR-SEC-05`).
- **Zero** unapproved act-level AI calls (`FR-AI-07`) and **zero** out-of-scope calls across 20 prompt-injection tests (`NFR-SEC-15`).
- **Zero** unbalanced journal entries (`SUM(debit) == SUM(credit)`) and **zero** successful edits/deletions of posted ledger records (`FR-LED-03`, `NFR-SEC-12`, `NFR-PRV-06`).
- **Performance (`NFR-PER-01`, `NFR-PER-04`):** Storefront TTFB p95 ≤ 800 ms at 50 concurrent VUs with error rate < 1%; Dashboard data load p95 ≤ 1000 ms.
- **Usability & A11y (`NFR-USE-04`, `NFR-USE-05`):** Zero serious or critical axe-core violations; checkout form has ≤ 8 input fields (`NFR-USE-03`).

---

## 5. Defect Severity & Priority Classification

| Severity | Definition | Example in Minivora |
|---|---|---|
| **S1 — Blocker** | System crash, cross-store data leak, double-charging, unbalanced ledger, or unapproved AI write action. | Store A reads Store B's orders; `OrderPaid` posts unbalanced journal entry. |
| **S2 — Critical** | Core commerce/finance/auth flow broken with no workaround. | Guest COD checkout returns 500; Egyptian phone regex rejects valid `010` number. |
| **S3 — Major** | Feature fails under edge/boundary condition or violates NFR threshold. | Storefront TTFB p95 hits 1200 ms under 50 VUs; Arabic search misses `ة`/`ه` variant. |
| **S4 — Minor** | Cosmetic, RTL alignment polish, or minor copy/translation issue. | Minor RTL padding glitch on mobile badge. |

---

## 6. Risks & Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Shared hosting throttling during load/stress tests | False positives or temporary account rate-limiting on Hostinger | Run heavy **stress, spike, and soak** tests against a local/VPS environment; run only the brief 5-min 50-VU `V-10` load test off-peak on Hostinger (per SRS §9.3). |
| Laravel route rate limiters (`throttle:20,1`, `throttle:60,1`) triggering `429` in k6 | Distorts error-rate metrics if all VUs share `127.0.0.1` | k6 scripts separate read-only storefront traffic from throttled write endpoints, track `429` as an explicit metric in stress tests, and support `X-Forwarded-For` IP simulation where configured. |
| Free AI provider rate limits during testing | Flaky tests or quota exhaustion | All automated Pest tests use fake LLM clients; AI-off failover (`NFR-REL-02`) is explicitly tested with all provider keys disabled. |
| Floating-point rounding bugs in money calculations | Trial balance mismatch | All monetary values stored and asserted as **integer piasters** (`1 EGP = 100 piasters`). |

---

## 7. Deliverables Inventory

| Deliverable | Path | Purpose |
|---|---|---|
| Software Test Plan | `docs/testing/TEST_PLAN.md` | Strategy, scope, environments, criteria, risks |
| Master Test Case Suite | `docs/testing/TEST_CASES.md` | 109 detailed test cases across all modules & NFRs |
| Test Cases Spreadsheet (CSV) | `docs/testing/test-cases.csv` | Importable into Excel / Google Sheets / Jira / TestRail |
| Requirements Traceability Matrix | `docs/testing/TRACEABILITY_MATRIX.md` | Maps SRS v1.8 IDs & `V-01..V-18` to `TC-*`, Pest, Playwright & k6 |
| k6 Performance Suite | `tests/load/` | Smoke, Load (`V-10`), Stress, Spike, Soak, and Dashboard load scripts + runbook |
