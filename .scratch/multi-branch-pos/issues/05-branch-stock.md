# 05: Branch stock balances and FIFO consumption

**What to build:** A sale or stock adjustment in one branch uses that branch's own material balance and FIFO batches, with inventory history and alerts scoped to the same branch. This is backend work only.

**Blocked by:** 01 — Default branch and authorized branch access.

**Status:** ready-for-agent

- [ ] Existing material quantities, batches, and inventory history are reconciled and attributed to the default branch without losing quantity or cost.
- [ ] Material definitions may remain shared, but balances, batches, adjustments, consumption, history, alerts, and inventory reads resolve to one branch.
- [ ] FIFO consumption in branch A never selects branch B's batch; insufficient stock in A fails even when B has stock.
- [ ] Every inventory movement keeps its branch, quantity, and actual cost so later reports can calculate cost of goods sold.
- [ ] Automated checks cover two-branch stock isolation, FIFO order, insufficient stock, and reconciliation of migrated balances.
