# 14: Enable second-branch operations safely

**What to build:** A restaurant can activate a second branch only after all POS paths and historical records are branch-safe, then operate both branches without data crossing between them. This is backend work only.

**Blocked by:** 02 — Independent shifts per branch; 03 — Tables belong to branches; 04 — Branch menu, price, and availability; 05 — Branch stock balances and FIFO consumption; 06 — Branch purchasing and stock receipts; 07 — Orders stay inside their shift's branch; 08 — Payments and refunds use the order's branch; 09 — Auditable stock transfers between branches; 10 — Branch and restaurant operating expenses; 11 — Real-time events scoped to one branch; 12 — Operational reports by branch and restaurant; 13 — Management operating profit by branch and restaurant.

**Status:** ready-for-agent

- [ ] Existing branch-owned history is fully backfilled and reconciled; mandatory relationships and branch-scoped uniqueness rules are enforced before the second branch opens.
- [ ] The owner can activate a second branch with its own staff, shift, table numbers, menu prices, and initially empty stock.
- [ ] Cross-branch probes cannot read or change orders, order items, payments, shifts, tables, stock, receipts, expenses, reports, or real-time channels by altering IDs or request parameters.
- [ ] Two branches can trade simultaneously, and closing one shift leaves the other branch running.
- [ ] A documented upgrade and rollback procedure states the migration order and how to verify old single-branch totals and stock before activation.
- [ ] Final automated verification covers the two-branch workflow and preserved single-branch data.
