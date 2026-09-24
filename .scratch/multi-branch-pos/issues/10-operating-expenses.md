# 10: Branch and restaurant operating expenses

**What to build:** Authorized managers can record operating expenses for a branch or for the restaurant as a whole. These expenses remain auditable and separate from stock purchases. This is backend work only.

**Blocked by:** 01 — Default branch and authorized branch access.

**Status:** ready-for-agent

- [ ] An expense records category, amount, incurred date, branch or restaurant-wide scope, actor, and creation time.
- [ ] Staff can read and post expenses only within their permission; restaurant-wide expense access requires explicit owner or head-office authorization.
- [ ] A posted expense is corrected by an auditable reversal or adjustment, not by silently deleting history.
- [ ] Restaurant-wide overhead remains unallocated in branch results until an allocation rule is approved.
- [ ] Stock purchases and stock transfers are not entered automatically as operating expenses.
- [ ] Automated checks cover branch isolation, owner visibility, corrections, and amount validation.
