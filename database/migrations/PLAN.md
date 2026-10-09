# Migration plan

Create migrations with `php artisan make:migration`, in this order. Every table except `stores` and `users` gets a `store_id` and an entry in `tests/Isolation/StoreIsolationTest.php`. Money columns are `BIGINT` piasters.

| # | Tables | Module |
|---|---|---|
| 1 | stores, store_domains, store_members, invitations, activity_logs | Platform, Accounts, Core |
| 2 | categories, products, product_variants, product_images, reviews, wishlists | Catalog |
| 3 | carts, cart_items, cart_events | Cart |
| 4 | leads, discount_codes, payments | Checkout |
| 5 | orders, order_items, order_status_histories, shipping_zones, return_requests, refunds | Orders |
| 6 | pages, sections, themes, media_files, page_versions | Builder |
| 7 | redirects, seo_settings | Seo |
| 8 | pixels, subscribers, abandoned_checkouts, ad_spends, campaigns, campaign_metrics | Marketing |
| 9 | page_views, visit_sessions | Analytics |
| 10 | accounts, journal_entries, journal_lines | Ledger |
| 11 | budgets, budget_lines, scenarios | Fpa |
| 12 | alerts, insights, approvals | Notifications, Ai |
