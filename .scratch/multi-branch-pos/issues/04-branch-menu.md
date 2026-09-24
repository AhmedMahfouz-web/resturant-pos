# 04: Branch menu, price, and availability

**What to build:** A restaurant can share product definitions while each branch controls whether a product is offered, its price, and its availability. This is backend work only.

**Blocked by:** 01 — Default branch and authorized branch access.

**Status:** ready-for-agent

- [ ] Existing products remain offered in the default branch at their existing prices after upgrade.
- [ ] Authorized management can configure a branch's offered products, prices, and availability; operational product reads return only that branch's offering.
- [ ] The same product can be priced differently in two branches or unavailable in one of them.
- [ ] Invalid prices, unauthorized changes, and access to another branch's menu are rejected.
- [ ] Existing order-item charged prices remain historical snapshots and are not rewritten when a menu price changes.
- [ ] Automated checks cover distinct branch offerings, permissions, and migration compatibility.
