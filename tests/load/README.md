# Minivora — k6 Load, Stress, Spike & Soak Testing Suite

Executable performance testing suite for **Minivora** aligned with **SRS v1.8** (`NFR-PER-01`, `NFR-PER-04`, `NFR-PER-05`, `NFR-SEC-17`, and verification check `V-10`).

---

## 1. Test Scripts Overview

| Script | Test Type | SRS Ref / TC ID | Profile | Key Pass Criteria |
|---|---|---|---|---|
| `smoke.js` | **Smoke** | `TC-PERF-001` | 1 VU × 30 s | 0% errors, all endpoints HTTP 200, `NFR-SEC-17` no-cache headers pass |
| `load-storefront.js` | **Load (`V-10`)** | `NFR-PER-01`, `TC-PERF-002` | 50 VUs × 5 min (1m ramp / 3m steady / 1m down) | Storefront TTFB p95 ≤ **800 ms**, Search p95 ≤ **500 ms**, 5xx < 1% |
| `load-dashboard-and-api.js` | **Load** | `NFR-PER-04`, `TC-PERF-003` | 15 Authenticated Owners × 3 min | Dashboard pages p95 ≤ **1000 ms**, error rate < 1% |
| `stress-storefront.js` | **Stress** | `TC-STRESS-001`, `TC-STRESS-003` | Progressive `50 → 100 → 150 → 200 → 250 VUs` + `20 VU` recovery | Identify latency knee, max RPS, `429` onset, and clean recovery |
| `spike-flash-sale.js` | **Spike** | `TC-SPIKE-001` | `10 → 150 VUs` in 10 s, hold 1 min, drop to `10 VUs` | 5xx < 5%, zero crashes, fast post-spike recovery |
| `soak-endurance.js` | **Soak / Endurance** | `TC-SOAK-001` | 30 VUs sustained × 30 min across 3 stores | TTFB p95 ≤ 800 ms, no memory/DB leak or upward latency drift |

---

## 2. Prerequisites

### Install k6
- **Ubuntu / Debian:**
  ```bash
  sudo gpg -k
  sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
  echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
  sudo apt-get update && sudo apt-get install -y k6
  ```
- **macOS:** `brew install k6`
- **Windows:** `winget install k6 --source winget` (or `choco install k6`)

### Prepare Local Minivora Server
```bash
composer install && npm install
npm run build
php artisan migrate:fresh --seed
php artisan optimize
php artisan serve --host=127.0.0.1 --port=8000
```
> **Note:** `php artisan serve` is single-threaded. For realistic **50–250 VU** load/stress tests, run Minivora behind **Nginx + PHP-FPM**, **Laravel Herd**, **FrankenPHP / Octane**, or **Laragon/Apache** so concurrent PHP workers can handle parallel requests.

---

## 3. Running the Tests

### Subdomain Mode (default: `http://demo.localhost:8000`)
```bash
# 1. Always start with the smoke test
k6 run tests/load/smoke.js

# 2. Official SRS v1.8 V-10 Load Test (50 VUs, 5 min)
k6 run tests/load/load-storefront.js

# 3. Authenticated Owner Dashboard Load Test (NFR-PER-04)
k6 run tests/load/load-dashboard-and-api.js

# 4. Progressive Stress Test (Local / VPS only — NOT shared hosting)
k6 run tests/load/stress-storefront.js

# 5. Flash-Sale Spike Test
k6 run tests/load/spike-flash-sale.js

# 6. 30-Minute Soak / Endurance Test
k6 run tests/load/soak-endurance.js
```

### Path Mode (`STOREFRONT_MODE=path`, e.g., `http://localhost:8000/s/demo`)
```bash
STOREFRONT_MODE=path k6 run tests/load/smoke.js
STOREFRONT_MODE=path k6 run tests/load/load-storefront.js
```

### Targeting Staging (e.g., Hostinger Off-Peak `V-10` Run)
```bash
APP_SCHEME=https APP_DOMAIN=yourdomain.com APP_PORT="" STORE_SLUGS="demo" \
  k6 run tests/load/load-storefront.js
```
> **SRS §9.3 Warning:** On Hostinger shared hosting, run **only** `smoke.js` and the brief 5-minute `load-storefront.js` off-peak. Do **not** run `stress-storefront.js`, `spike-flash-sale.js`, or `soak-endurance.js` on shared hosting.

---

## 4. Exporting Reports for Your Assignment

Export a machine-readable JSON summary (great for charts and tables in your assignment report):
```bash
mkdir -p tests/load/results
k6 run --summary-export=tests/load/results/load-storefront-summary.json tests/load/load-storefront.js
k6 run --summary-export=tests/load/results/stress-summary.json tests/load/stress-storefront.js
k6 run --summary-export=tests/load/results/spike-summary.json tests/load/spike-flash-sale.js
```

Or launch k6's built-in **Web Dashboard** and save an interactive HTML report directly:
```bash
K6_WEB_DASHBOARD=true K6_WEB_DASHBOARD_EXPORT=tests/load/results/load-report.html \
  k6 run tests/load/load-storefront.js
```

---

## 5. Configurable Environment Variables

| Variable | Default | Description |
|---|---|---|
| `APP_SCHEME` | `http` | `http` or `https` |
| `APP_DOMAIN` | `localhost` | Base domain |
| `APP_PORT` | `8000` | Port (set `""` for standard 80/443) |
| `STOREFRONT_MODE` | `subdomain` | `subdomain` (`<store>.<domain>`) or `path` (`/s/<store>`) |
| `STORE_SLUG` | `demo` | Primary store for smoke & spike tests |
| `STORE_SLUGS` | `demo,nile-crafts,cairo-leather` | Comma-separated stores for multi-tenant load/stress/soak |
| `TARGET_VUS` | `50` | Steady-state VU count for `load-storefront.js` |
| `SPIKE_VUS` | `150` | Peak VU count for `spike-flash-sale.js` |
| `SOAK_VUS` / `SOAK_DURATION` | `30` / `30m` | VU count & duration for `soak-endurance.js` |
| `OWNER_EMAILS` / `OWNER_PASSWORD` | `owner0..2@minivora.test` / `password` | Seeded owner credentials for `load-dashboard-and-api.js` |
