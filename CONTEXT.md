# Restaurant POS

This context describes the businesses and physical locations served by Maresto.

## Language

**Restaurant**:
The customer business that owns one POS subscription, backend subdomain, and database. A restaurant can have one or more branches.
_Avoid_: Tenant when talking to restaurant staff.

**Branch**:
A physical operating location of a restaurant with its own sales, shifts, tables, and stock. Branches of the same restaurant share its backend and database.
_Avoid_: Tenant, deployment.

**Shift**:
An operating period belonging to one branch. Orders made during the period belong to that branch through the shift.

**Branch user**:
A restaurant staff member authorized to operate one or more specified branches. Restaurant-wide owner access is a separate permission.

**Branch expense**:
An operating cost recorded against one branch, separate from stock purchases and the cost of ingredients used in sales.
