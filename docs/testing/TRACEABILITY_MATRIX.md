# Minivora — Requirements Traceability Matrix (RTM)

Maps **Minivora SRS v1.8** verification checks (`V-01`–`V-18`) and requirement groups (`FR-*`, `NFR-*`) to:
1. Formal Assignment Test Cases ([`TEST_CASES.md`](TEST_CASES.md) / [`test-cases.csv`](test-cases.csv))
2. Automated Backend & Isolation Tests (`tests/`)
3. Automated Browser E2E Tests (`e2e/`)
4. Executable Performance / Load / Stress / Spike / Soak Scripts (`tests/load/`)

---

## 1. Verification Check Mapping (SRS §9.3 `V-01` – `V-18`)

| Check ID | SRS Scope | Assignment Test Cases (`TC-*`) | Automated Repo Tests / Scripts | Pass Criterion (SRS v1.8) |
|---|---|---|---|---|
| **V-01** | Every Must FR | `TC-ONB-001..008`, `TC-ACC-001..008`, `TC-CAT-001..009`, `TC-CRT-001..006`, `TC-PAY-001..010`, `TC-ORD-001..008`, `TC-LED-001..008`, `TC-BLD-001..008`, `TC-AI-001..010` | `tests/Feature/**`, `tests/Feature/Platform/MustFrClosureTest.php` | Every Must FR has ≥ 1 passing tagged test |
| **V-02** | `FR-CAT`, `FR-CRT`, `FR-PAY`, `FR-ORD`, `NFR-USE-03` | `TC-CRT-001`, `TC-PAY-001`, `TC-ORD-005`, `TC-USE-003` | `e2e/shopper-checkout.spec.ts`, `e2e/accessibility-and-checkout.spec.ts` | Browse → COD checkout, track order, staff fulfil pass; checkout ≤ 8 fields |
| **V-03** | `FR-ONB`, `NFR-USE-01`, `NFR-USE-02` | `TC-ONB-001`, `TC-USE-001`, `TC-USE-002` | `e2e/owner-create-store.spec.ts` + 5-owner usability session | Dashboard ≤ 60 s; first product ≤ 15 min; SUS ≥ 68 |
| **V-04** | `FR-PAY`, `NFR-SEC-14`, `NFR-OPS-03` | `TC-PAY-001..010`, `TC-SEC-007`, `TC-SEC-008` | `tests/Feature/Checkout/CheckoutTest.php`, `StorefrontCheckoutFlowTest.php` | Each order paid once; ledger balanced; 0 real transactions |
| **V-05** | `FR-LED`, `NFR-SEC-12`, `NFR-PRV-06`, `NFR-MNT-02` | `TC-LED-001..008` | `tests/Unit/Ledger/BalancedEntryTest.php`, `PostingRulesTest.php`, `tests/Feature/Ledger/FinanceFlowTest.php`, `LedgerVerificationTest.php` | Debits == Credits; P&L matches hand calc; edits/deletes refused |
| **V-06** | `FR-SEO` | `TC-BLD-005..008` | `tests/Feature/Seo/SeoTest.php` + Lighthouse SEO | Zero structured-data errors; Lighthouse SEO ≥ 90 |
| **V-07** | `FR-ONB-09`, SRS §2.8 | `TC-ONB-008` | `tests/Feature/Importers/AdornaImportTest.php` | Imported totals match Adorna records to the exact piaster |
| **V-08** | `FR-PRO`, `FR-MI`, `NFR-AIQ-05..07`, `NFR-USE-06` | `TC-AI-009`, `TC-AI-010` | `tests/Feature/Ai/AiTeamAndProactiveTest.php` + 3-week shadow run | `NFR-AIQ-05` met; `NFR-AIQ-06` & `-07` reported |
| **V-09** | `FR-AI`, `FR-FPA`, `NFR-AIQ-01..04,09,10`, `NFR-PER-06,07` | `TC-AI-001..008`, `TC-LED-007`, `TC-LED-008`, `TC-PERF-007` | `tests/Unit/Ai/BusinessRulesTest.php`, `NumberGuardTest.php`, `FunctionRegistryTest.php`, `app/Ai/Eval/` | ≥ 85% correct function; 100% correct figures; interpretation shown |
| **V-10** | `NFR-PER-01..05` | `TC-PERF-001..006`, `TC-STRESS-001..003`, `TC-SPIKE-001`, `TC-SOAK-001` | `tests/load/load-storefront.js`, `load-dashboard-and-api.js`, `stress-storefront.js`, `spike-flash-sale.js`, `soak-endurance.js` | Storefront TTFB p95 ≤ 800 ms @ 50 VUs; Dashboard p95 ≤ 1000 ms; Lighthouse mobile ≥ 80 |
| **V-11** | `NFR-SEC` (except `-12`, `-14`) | `TC-SEC-001..010`, `TC-AI-004` | `tests/Isolation/StoreIsolationTest.php`, `tests/Feature/Security/SecurityHardeningTest.php`, `tests/Unit/Core/PermissionMatrixTest.php`, `ActivityLogTest.php` | Zero cross-store reads; 100% RBAC matrix enforced; 0/20 prompt injections succeed |
| **V-12** | `NFR-USE-04`, `NFR-USE-05` | `TC-USE-004`, `TC-USE-005`, `TC-USE-006` | `e2e/accessibility-and-checkout.spec.ts` | Zero serious/critical axe issues; keyboard-only checkout & RTL pass |
| **V-13** | `NFR-REL-01`, `NFR-REL-07` | `TC-REL-001` | `tests/Feature/Reliability/ReliabilityAndOpsTest.php` + Backblaze B2 restore drill | Full DB restore succeeds within RTO (`TBD-11`) |
| **V-14** | `NFR-REL-02` | `TC-REL-002` | `tests/Feature/Reliability/ReliabilityAndOpsTest.php`, `tests/Feature/Ai/ChatTest.php` | Storefront/checkout/dashboard work with all AI keys disabled |
| **V-15** | `FR-AI-07`, `NFR-AIQ-08` | `TC-AI-003` | `tests/Feature/Ai/AiTeamAndProactiveTest.php` | Every unapproved act-level AI call refused |
| **V-16** | `NFR-OPS`, `NFR-MNT-01,03`, `NFR-SCL`, `NFR-REL-08` | `TC-REL-003`, `TC-REL-004`, `TC-PERF-008` | `tests/Feature/Reliability/ReliabilityAndOpsTest.php` | Job overlap skipped & logged; stateless storage verified; AI daily cap enforced |
| **V-17** | `NFR-PRV`, `FR-ACC-09`, `FR-ANA-06` | `TC-ACC-007`, `TC-ACC-008`, `TC-AI-005`, `TC-AI-006`, `TC-BLD-008` | `tests/Feature/Accounts/TwoFactorAndPrivacyTest.php`, `tests/Feature/Analytics/AnalyticsAndMarketingTest.php` | Zero PII in LLM prompts; no tracking/AI before consent; export/delete works |
| **V-18** | `NFR-REL-05`, `NFR-REL-06` | `TC-REL-005` | UptimeRobot monitor + DNS SPF/DKIM/DMARC header check | Monitor active (5-min interval); SPF/DKIM/DMARC pass |

---

## 2. Requirement Category Coverage Summary

| Category | SRS v1.8 Count | Covered Test Case IDs |
|---|---|---|
| `FR-ONB` (Onboarding & Stores) | 11 | `TC-ONB-001` – `TC-ONB-008` |
| `FR-ACC` (Accounts, Roles, 2FA, Privacy) | 9 | `TC-ACC-001` – `TC-ACC-008` |
| `FR-CAT` (Catalog, Variants, Arabic Search) | 18 | `TC-CAT-001` – `TC-CAT-009` |
| `FR-CRT` (Cart & Merge) | 5 | `TC-CRT-001` – `TC-CRT-006` |
| `FR-PAY` (Checkout, Gateways, Discounts, Risk) | 12 | `TC-PAY-001` – `TC-PAY-010` |
| `FR-ORD` & `FR-NTF` (Orders, Shipping, Returns, Alerts) | 18 | `TC-ORD-001` – `TC-ORD-008` |
| `FR-LED` & `FR-FPA` (Double-Entry Ledger & FP&A) | 19 | `TC-LED-001` – `TC-LED-008` |
| `FR-BLD`, `FR-SEO`, `FR-MKT`, `FR-MI`, `FR-ANA` | 59 | `TC-BLD-001` – `TC-BLD-008` |
| `FR-AI`, `FR-PRO`, `NFR-AIQ` (AI Team & Proactive) | 34 | `TC-AI-001` – `TC-AI-010` |
| `NFR-SEC` & `NFR-PRV` (Security, Isolation, Privacy) | 23 | `TC-SEC-001` – `TC-SEC-010` |
| `NFR-USE` (Usability, RTL, Accessibility) | 6 | `TC-USE-001` – `TC-USE-006` |
| `NFR-PER`, `NFR-SCL`, `NFR-REL`, `NFR-OPS`, `NFR-MNT` | 27 | `TC-PERF-001` – `TC-PERF-008`, `TC-STRESS-001` – `TC-STRESS-003`, `TC-SPIKE-001`, `TC-SOAK-001`, `TC-REL-001` – `TC-REL-005` |
| **Total** | **241** | **109 Test Cases** |
