# 0002: All stores in one app and one database

**Status:** accepted · Oct 2026

**Decision:** Shopify-style multi-store: every store is a row in `stores`, every store-owned table has `store_id`, and a global scope filters every query. Store subdomains all point to the same app via a wildcard DNS record.

**Why:** creating a store needs no server or hosting setup; one deploy updates every store.

**Guardrail:** `tests/Isolation/StoreIsolationTest.php` must cover every store-owned model.
