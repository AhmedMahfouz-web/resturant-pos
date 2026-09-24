# 12: Operational reports by branch and restaurant

**What to build:** An authorized owner can compare branch sales, payments, shifts, and stock over one period and see a restaurant total; branch staff see only permitted data. This is backend work only.

**Blocked by:** 06 — Branch purchasing and stock receipts; 08 — Payments and refunds use the order's branch.

**Status:** ready-for-agent

- [ ] Existing operational reports and dashboards accept an authorized branch scope; a restaurant-wide view is restricted to the owner or explicit head-office permission.
- [ ] Sales, refunds, payments, shifts, purchasing, stock, and related metrics use the same branch ownership and date-range rules.
- [ ] The all-branch total aggregates branch-attributed facts once, without a duplicated total ledger; A + B reconciles with the restaurant figure.
- [ ] A branch employee cannot expose another branch through filters, IDs, exports, or dashboard endpoints.
- [ ] Historical single-branch report totals remain unchanged after backfill.
- [ ] Automated checks cover filtering, authorization, aggregation, and date boundaries.
