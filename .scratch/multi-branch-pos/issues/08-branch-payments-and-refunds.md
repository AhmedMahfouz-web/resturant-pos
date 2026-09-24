# 08: Payments and refunds use the order's branch

**What to build:** A payment or refund belongs to the original order's branch and cannot be moved or accessed by choosing another branch. This is backend work only.

**Blocked by:** 07 — Orders stay inside their shift's branch.

**Status:** ready-for-agent

- [ ] Payment creation, refund, lookup, and transaction history use the authorized order's immutable branch.
- [ ] A staff user cannot pay, refund, or read an order from another branch by changing an order, payment, or transaction ID.
- [ ] Refunds reverse financial and stock effects only in the original branch, with no duplicate reversal on retries.
- [ ] Existing payment and refund amounts remain unchanged after historical order backfill.
- [ ] Automated checks cover cross-branch IDs, original-branch refunds, retry behavior, and single-branch compatibility.
