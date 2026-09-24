# 06: Branch purchasing and stock receipts

**What to build:** A branch can order and receive materials into its own stock, and its purchasing history and statistics stay within that branch. This is backend work only.

**Blocked by:** 05 — Branch stock balances and FIFO consumption.

**Status:** ready-for-agent

- [ ] Existing purchase orders and receipts are attributed to the default branch without duplicating stock or cost.
- [ ] Purchase order and receipt creation, update, lookup, and statistics are scoped to the authorized branch; foreign branch IDs are rejected.
- [ ] Receiving material increases only the destination branch's balance and FIFO batches at the recorded acquisition cost.
- [ ] Correcting or reversing a receipt preserves auditable inventory effects in its original branch.
- [ ] Automated checks cover cross-branch access, stock changes, and historic receipt backfill.
