# 03: Tables belong to branches

**What to build:** Each branch manages and sees its own dining tables. The same table number may exist at another branch. This is backend work only.

**Blocked by:** 01 — Default branch and authorized branch access.

**Status:** ready-for-agent

- [ ] Existing tables are attributed to the default branch without changing their state or numbers.
- [ ] Listing, lookup, creation, updates, and deletion are scoped to the authorized active branch; foreign branch IDs are rejected.
- [ ] Table numbers are unique within one branch, and two branches may each have table `1`.
- [ ] Table availability/status changes cannot affect another branch's table.
- [ ] Automated checks cover duplicate numbers across branches, duplicates within a branch, and cross-branch access.
