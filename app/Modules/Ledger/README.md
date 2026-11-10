# Ledger

Double-entry books for every store (FR-LED-01..08, UC-10, UC-11). Every figure on the finance
pages, in exports and in AI answers is computed from posted journal lines.

Other modules may only call this module's `Services/`. Never use its `Models/` from outside.

## How money gets into the books

| Business event | Debit | Credit | Where |
|---|---|---|---|
| Card / wallet payment captured | Gateway clearing (1020), Sales discounts | Sales revenue, Shipping income, VAT payable | `OrderPaid` → `OrderPosting` |
| Gateway fee (percent + fixed, Finance settings) | Payment fees (5100) | Gateway clearing | same |
| InstaPay verified | Bank (1010) | Sales revenue … | same |
| Cash on delivery collected (order delivered) | Cash (1000) | Sales revenue … | same |
| Order shipped | Cost of goods sold (5000) | Inventory (1200) | `OrderShipped` |
| Refund (cancelled / refunded / returned after payment) | Sales returns (4910), VAT payable | where the money came in | `OrderCancelled`, `OrderReturned`, `RefundIssued` |
| Items back on the shelf (cancelled / returned after shipping) | Inventory | Cost of goods sold | same |
| Expense paid now | Expense account, VAT recoverable (1300) | Cash, Bank or Credit card payable | `ExpenseService::record` |
| Expense paid later (bill) | Expense account, VAT recoverable | Accounts payable (2000) | same, then `markPaid`: Dr payable, Cr cash/bank |
| Transfer / gateway payout | Bank (net), Bank charges | Gateway clearing | `JournalController::transfer` |
| Anything else (capital, loans, equipment, depreciation) | manual entry | manual entry | Ledger › New entry |

Rules live in `Services/PostingRules.php` as pure functions (unit tested). `OrderPosting::sync()`
looks at an order snapshot and posts whatever it should have and doesn't yet, each under a
source key (`order:12:sale`), so a repeated event or the "catch up" button never posts twice.

VAT: prices in Egypt are shown VAT-inclusive, so a VAT-registered store carves
`total × r / (1 + r)` out of each sale (14% standard rate; registration is required once taxable
sales pass EGP 500,000 in 12 months). Confirm the treatment with an accountant before going live.

## Guarantees

- **Balanced** (FR-LED-03): `LedgerService::post` refuses one-sided, zero, negative or unbalanced
  entries; `JournalEntry` refuses an unbalanced header; on MySQL/Postgres a `CHECK` constraint does too.
- **Append-only**: entries are never edited; mistakes are reversed with a mirror entry.
- **Closed months** (FR-LED-07): `periods` locks a month. Manual entries dated in it are refused;
  automatic ones (a late refund) land on the first open day. `PeriodService::checklist()` is the
  close checklist: orders posted, books balance, previous month closed, bank matched (confirmed by
  the person), stock at cost vs inventory, unpaid bills.

## Statements (`StatementService`)

- Profit and loss: net revenue − COGS = gross profit; − variable costs (fees, shipping, packaging)
  = contribution margin; − operating costs = net profit.
- Balance sheet: assets = liabilities + equity, with profit to date computed (no year-end closing entry).
- Cash flow: direct method over cash, bank and gateway clearing, grouped as operating, investing, financing.
- Trial balance, VAT summary, monthly series, and any account's activity with a running balance.

All CSV exports carry a UTF-8 BOM (Arabic opens correctly in Excel) and neutralise formula cells.
