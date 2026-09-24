# 02: Independent shifts per branch

**What to build:** Staff can start, inspect, and close shifts for their authorized branch without affecting another branch's shift or login. This is backend work only.

**Blocked by:** 01 — Default branch and authorized branch access.

**Status:** ready-for-agent

- [ ] Existing shifts are attributed to the default branch without changing their amounts or times.
- [ ] Two branches can have open shifts at the same time; finding an open shift is scoped to the active branch.
- [ ] Shift creation, details, closing, and related cash totals reject an unauthorized branch or a shift from another branch.
- [ ] Closing one shift leaves the other branch's shift open and does not invalidate every restaurant user's token.
- [ ] Automated checks cover simultaneous open shifts, cross-branch IDs, and single-branch compatibility.
