# Fpa

Planning on top of the ledger (FR-FPA-01..05). Reads figures only through Ledger services.

Other modules may only call this module's `Services/`. Never use its `Models/` from outside.

- **Budgets** (`VarianceService`): a planned amount per P&L account per month, compared with
  actuals. A month still running is judged against the plan so far (budget × days gone ÷ days in
  month). Revenue above plan is favourable; costs and returns above plan are not.
- **Cash forecast** (`ForecastService::cash`): next month's net cash flow by simple exponential
  smoothing of the last six complete months, ŷ(t+1) = α·y(t) + (1 − α)·ŷ(t), α = 0.5; projected cash
  = cash now + k·ŷ − bills falling due. Warns when it drops below the store's safety level.
- **Break-even**: BE = F / (1 − c/p), F = average fixed costs, c/p = (COGS + variable costs) ÷ net
  revenue, over the last three complete months.
- **What-if** (computed in the browser from `whatIfBase()`): price, order volume, product cost and
  ad spend applied to last month's real numbers.
- **Pricing**: p = c / (1 − m).
