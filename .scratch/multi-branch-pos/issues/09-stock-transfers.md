# 09: Auditable stock transfers between branches

**What to build:** Authorized staff can transfer stock between two branches as one auditable operation while preserving its quantity and cost. This is backend work only.

**Blocked by:** 05 — Branch stock balances and FIFO consumption.

**Status:** ready-for-agent

- [ ] A transfer records matching outbound and inbound movements with source, destination, actor, quantity, date, and carried stock cost.
- [ ] The operation is atomic: insufficient source stock or a failed destination update leaves both branches unchanged.
- [ ] The destination receives its own stock batches; later FIFO consumption still uses only destination stock.
- [ ] Both branches can see their side of the authorized transfer, and another branch cannot alter it.
- [ ] Transfer value is excluded from restaurant sales and operating expenses.
- [ ] Automated checks cover balances and costs before/after transfer, rollback on failure, and permission checks.
