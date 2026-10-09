# 0001: Laravel + MySQL, starting on Hostinger shared hosting

**Status:** accepted · Oct 2026

**Decision:** build Minivora with Laravel (PHP), MySQL and React + TypeScript (Inertia), deployed first to the team's existing Hostinger web hosting.

**Why:** every Hostinger/cPanel plan runs PHP + MySQL with no Docker or root access; Adorna EG's PHP code can be reused; the same code moves to a VPS or AWS/Google Cloud later.

**Trade-offs:** no PostgreSQL Row-Level Security (store isolation is enforced in the app and tested); no pgvector (search by meaning deferred to stage 2); background work runs from cron in short batches.
