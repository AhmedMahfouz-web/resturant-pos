# Multi-branch POS specification

## Goal and boundary

One restaurant may run several branches without creating another backend subdomain, deployment, subscription, or database. All its branches live in that restaurant's existing POS database at `{restaurant}.maresto.eminent-studio.com`. The restaurant owner can view each branch separately and a restaurant-wide summary. A branch user must not operate another branch without permission.

This is a design specification for the next implementation; the current branch does not yet implement multi-branch behavior. Frontend code is owned by a separate developer and is not changed here.

## Confirmed domain decisions

- **Restaurant** is the paying customer and deployment/database boundary. Subscription expiry applies to the restaurant, not each branch.
- **Branch** is a physical operating location inside the restaurant database. It has a stable ID and name. One restaurant starts with one default branch and may add more.
- The product/menu catalog can be shared at restaurant level, but **price and availability vary by branch**. An order records the price actually charged so later catalog changes cannot rewrite sales history.
- Stock is physically held by a branch. A material balance, FIFO batch, receipt, adjustment, consumption, alert, and stock report must belong to or be resolved for a branch. Transfers between branches require an explicit outgoing/incoming record; they cannot be simulated by changing one global material quantity.
- Owner/authorized head-office reporting can choose one branch or all branches. The all-branch view must aggregate from branch-attributed facts, not from a second duplicated ledger.
- The owner also requires expense entry and a profit view for each branch and the restaurant overall. The existing POS has no general expense ledger, so this is new financial work rather than a filter on existing reports.
- The requested profit view is a **management report**, not a statutory accounting system with a general ledger, balance sheet, depreciation, and tax filings.

## Existing code that cannot be reused unchanged

- There is no `branch_id` or branch model. Orders, shifts, tables, material balances, batches, receipts, inventory transactions, and reports currently have no branch boundary.
- `ShiftController::startShift()` returns the first open shift for the whole database. `closeShift()` invalidates every user's token in that database. Both behaviors conflict with independent branch shifts.
- `OrderController` reads live/history orders without a branch filter. Order creation accepts `table_id` and `shift_id` without checking that they belong to the same location.
- `Material.quantity` and `StockBatch::consumeForMaterial()` represent one stock pool. Adding `branch_id` only to orders would still let a sale in one branch consume another branch's stock.
- `TableController` validates `Table.number` as unique across the whole database. Branches must be allowed to reuse table numbers, with uniqueness enforced per branch.
- Sales, payment, inventory, and dashboard reporting currently query the restaurant database as one scope. This is suitable only for an authorized all-branch view, not a branch employee view.
- Current real-time channels use names shared inside the restaurant deployment, such as `orders` and `inventory`; branch events/subscriptions need branch scoping.
- `transactions` records sales/refunds only; there is no general expense ledger. The current profitability report estimates product/recipe cost from recipes and cannot yet calculate a reliable branch or restaurant net profit.

## Required data model

1. Add `branches` with a unique restaurant-local code/name as appropriate and an active flag. Never delete a branch that has historical transactions; deactivate it.
2. Attribute each operational fact to a branch at its source. At minimum: shifts, orders, tables, stock locations/balances, batches, receipts, stock adjustments and transactions. Payments and order items may inherit branch through their immutable order, but reports must join through that order consistently.
3. Store branch-specific product offering/price/availability separately from the restaurant-level product definition. Keep historical order-item price snapshots.
4. Represent stock by material **and branch**. FIFO consumption must select only that branch's batches. Cross-branch transfers must move quantity and cost between two branch ledgers atomically.
   A refund or reversal uses the original order's branch; changing the active branch cannot move historical sale or stock effects.
5. Represent which branches a user can operate. The server validates the active branch against those assignments on every branch-scoped request. A restaurant-wide role can view approved aggregate reports; possession of a branch ID in browser storage or a request is never authorization.
6. Keep the subscription and restaurant JWT boundary from [CLOUD-TENANCY-SPEC.md](CLOUD-TENANCY-SPEC.md). A token from another restaurant still fails before branch authorization.
7. Record operating expenses with category, amount, incurred date, branch or restaurant-wide scope, and who posted them. Posted expense corrections use an auditable reversal/adjustment rather than silently deleting history. Restaurant-wide overhead is shown separately; do not invent branch allocations without an agreed rule.

## Financial reports

- Show sales, refunds, net sales, payments, cost of sold ingredients, operating expenses, and profit for each branch and for the restaurant total over the same period. `orders.total_amount` currently includes tax and service, so it cannot be used by itself as tax-exclusive net sales.
- Calculate cost of sold ingredients from the actual branch-scoped inventory consumption/cost ledger for completed orders, not a later recipe-price estimate. Purchasing stock increases inventory; it is not also counted as an operating expense at purchase time. Transfers between branches are not sales or restaurant expenses. This avoids double counting stock costs. See the [IFRS Foundation's IAS 2 inventory guidance](https://www.ifrs.org/issued-standards/list-of-standards/ias-2-inventories/) for the distinction between inventory and expense recognition.
- An expense recorded for the restaurant as a whole appears in the overall result and separately as unallocated overhead. A branch result does not silently absorb it. If the owner wants allocated branch profit, agree the allocation rule first.
- Define the management result as tax-exclusive net sales after refunds, less actual sold-ingredient cost, recorded operating expenses, and stock waste/loss. Show tax collected and service charges on separate lines; agree how retained service charges and staff payouts affect the result before including them. Label the result **management operating profit** so it is not mistaken for a statutory net-profit figure.

## API and user flow

- After restaurant selection and login, the UI displays the user's permitted branches. A user with one branch may enter it directly; a user with several selects one. The branch choice may be stored in the browser for convenience, but the backend verifies it for every operation.
- Operational APIs use exactly one authorized active branch. The backend derives that scope once per request and applies it to all reads, writes, payments, shifts, inventory, and real-time events. An order/table/shift/payment ID from another branch must be rejected even if it exists in the same DB.
- Head-office reports accept an authorized branch filter or an explicit all-branch mode. Return per-branch metrics plus the restaurant total over the same date range and calculation rules.
- Branch-specific prices and availability are read at order creation and validated again server-side. A client-supplied price or branch ID must not decide the charged amount or bypass branch permission.

## Migration and rollout

1. Create one default branch for every existing restaurant database.
2. Backfill every historical shift, order, table, and stock/ledger fact into that default branch without changing amounts or quantities. Reconcile material balances and existing FIFO batches before making branch IDs mandatory.
3. Add branch constraints/indexes and update every order, shift, payment, inventory, reporting, scheduler, and broadcast path before enabling a second branch. Do not expose a partial migration where only some paths are branch-aware.
4. Migrate the globally unique table-number constraint to uniqueness within a branch. Check other business codes for the same problem before allowing duplicate numbers/codes across branches.
5. Create the second branch with empty stock and its own tables/shifts. Shared products have explicit branch prices and availability before selling there.

## Acceptance criteria

- Two branches can have open shifts and table `1` at the same time. Closing one branch's shift leaves the other branch's shift and users active.
- A staff member cannot read or mutate another branch's order, payment, table, or inventory by changing an ID or request parameter.
- A sale in branch A decreases only branch A's FIFO batches and balance. An insufficient balance in A cannot use stock held in B.
- Moving stock from A to B records matching outbound and inbound effects without counting the transfer as restaurant revenue.
- The same product can have different prices/availability in A and B; an order keeps its historical charged price.
- Authorized owner reports show A, B, and A+B totals consistently for the same period. Unauthorized staff cannot request the all-branch view.
- Branch expenses appear in their branch result; restaurant-wide expenses appear once in the overall result. Stock purchases and inter-branch transfers are not double-counted as expenses or revenue.
- Existing single-branch restaurants keep their historical sales and stock after default-branch backfill.

## Open decision

- Confirm whether a staff member may be assigned to multiple branches or must belong to one branch. The authorization model and login/shift flow depend on this choice.
- Confirm how retained service charges and any staff payouts should enter the management profit calculation. Restaurant-wide overhead is unallocated in branch views unless a later allocation rule is approved.
