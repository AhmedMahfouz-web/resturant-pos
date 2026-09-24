# 01: Default branch and authorized branch access

**What to build:** An existing restaurant can continue operating as one branch, while its owner can prepare additional branches and assign staff to the branches they may use. The backend resolves one authorized active branch for each operational request. This is backend work only; the separate frontend is outside this ticket.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] An upgrade and a fresh installation each have exactly one default branch; running the upgrade again does not duplicate it or change POS history.
- [ ] Existing staff keep access to the default branch. A staff member may be assigned to one or more branches; restaurant-wide owner permission is explicit.
- [ ] An owner can list, create, name, deactivate, and assign staff to branches through authorized API operations. A branch with history cannot be deleted.
- [ ] The API exposes a user's permitted branches and rejects an unknown, inactive, or unauthorized branch before performing an operational read or write.
- [ ] Legacy clients use the default branch only while it is the sole active branch; a second branch cannot operate until ticket 14 enables it.
- [ ] Automated checks cover authorized access, forbidden branch selection, idempotent setup, and unchanged single-branch behavior.
