# 13: Management operating profit by branch and restaurant

**What to build:** The owner sees a consistent management profit view for each branch and the whole restaurant, based on actual sold-ingredient cost and recorded expenses. This is backend work only.

**Blocked by:** 09 — Auditable stock transfers between branches; 10 — Branch and restaurant operating expenses; 12 — Operational reports by branch and restaurant.

**Status:** ready-for-agent

- [ ] For one period, the report shows tax-exclusive sales after refunds, actual branch inventory cost consumed by completed sales, stock waste/loss, operating expenses, and management operating profit.
- [ ] It shows tax collected and service charges on separate lines. Staff payouts and retained service charge treatment remain outside profit until the owner approves a rule.
- [ ] Branch expenses affect only their branch; restaurant-wide overhead appears once in the overall result and separately as unallocated overhead.
- [ ] Stock purchases are inventory until consumed or written off. Inter-branch transfers are neither sales nor restaurant expense and do not double-count cost.
- [ ] Restaurant total reconciles with branch figures plus unallocated overhead; unauthorized staff cannot request it.
- [ ] Automated checks cover refunds, actual FIFO costs, waste, overhead, transfers, and date boundaries.
