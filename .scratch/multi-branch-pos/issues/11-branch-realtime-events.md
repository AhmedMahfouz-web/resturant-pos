# 11: Real-time events scoped to one branch

**What to build:** Staff receive live order and inventory changes for their authorized branch without seeing another branch's activity. This is backend work only; the separate frontend developer handles client subscriptions.

**Blocked by:** 08 — Payments and refunds use the order's branch.

**Status:** ready-for-agent

- [ ] Order, payment, and inventory events carry the authoritative branch of their underlying record.
- [ ] Branch subscriptions require server-side authentication and permission checks; changing a channel name or branch ID does not expose another branch's events.
- [ ] A branch A order or stock movement is not published on a channel accessible to branch B staff.
- [ ] Existing single-branch clients have a documented transition to the new channel contract without changing the frontend in this ticket.
- [ ] Automated checks cover authorized and rejected subscriptions plus branch-specific event routing.
