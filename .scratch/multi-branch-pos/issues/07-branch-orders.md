# 07: Orders stay inside their shift's branch

**What to build:** Staff can create and manage orders only in their authorized branch. Every order keeps the branch of its shift and uses that branch's table, menu price, and stock. This is backend work only.

**Blocked by:** 02 — Independent shifts per branch; 03 — Tables belong to branches; 04 — Branch menu, price, and availability; 05 — Branch stock balances and FIFO consumption.

**Status:** ready-for-agent

- [ ] Existing orders are backfilled to the branch of their shift; mismatches are reported and resolved before the relationship becomes mandatory.
- [ ] New orders keep an immutable branch equal to their shift's branch; dine-in tables and staff assignment must be valid in that branch.
- [ ] The backend verifies offered products and current branch prices at order creation and item changes; a client-supplied price cannot override them. Charged prices remain stored on order items.
- [ ] Order lists, details, item changes, discounts, split, cancellation, and completion reject access through another branch's IDs.
- [ ] Stock consumption or reversal follows the order's original branch even if a user changes active branch later.
- [ ] Automated checks cover mixed-branch IDs, historical prices, stock isolation, and existing single-branch order behavior.
