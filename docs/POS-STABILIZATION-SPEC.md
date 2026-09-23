# POS reliability and inventory specification

## Goal

Make the existing restaurant POS API boot reliably and keep order, payment, and ingredient stock records consistent, while preserving the current endpoints and successful response shapes.

## Current domain rules

- An order becomes completed when its payment succeeds.
- A completed order consumes the recipe materials for its items.
- A product without a recipe does not consume material stock.
- A material receipt already updates the material quantity, creates a stock batch, and records an inventory transaction through the `MaterialReceipt` model event. This remains the single receipt posting path; it must not be repeated in the controller or service.
- The `Order` completion transition already owns ingredient consumption. Consumption follows recipe `material_quantity`, material conversion rate, and FIFO batch order; it updates material balances, batches, and the transaction ledger together.
- Inventory transaction quantities are positive magnitudes; the `type` field distinguishes receipt from consumption.
- Canceling an order with `waste` set continues to consume the wasted ingredients, using the same inventory rules.

## Required changes

1. Fix the malformed import in `SupplierPerformanceController` so Artisan can load and list routes.
2. Keep committed database configuration free of credentials and read the default connection and connection details from environment variables. Honor the standard `DB_DATABASE` setting.
3. Fix inventory dashboard stock valuation to total the already eager-loaded available batches.
4. Keep the existing `Order` FIFO implementation as the single consumption path for completed orders and canceled orders explicitly marked as waste. Remove the duplicate legacy decrement dispatch from payment. Consumption must be atomic and idempotent per order item.
5. Make payment input errors and missing orders return controlled validation/not-found responses. Payment creation, order completion, and stock consumption must be atomic. A repeated payment must not duplicate payment or stock records. Keep the existing overpayment/change behavior.
6. Keep inventory balances compatible with installations that have material quantities but no matching stock batches: reconcile available batches to the material balance before consuming. Create a batch for an unbatched opening balance, or reduce excess legacy batches FIFO when aggregate stock is lower. Do not change the aggregate balance during reconciliation.
7. Order history endpoints must return an empty paginated result when no shift exists instead of dereferencing a missing shift.
8. Add focused automated coverage for boot/route loading, dashboard valuation, order consumption (including no recipe and duplicate completion), insufficient stock rollback, payment validation, and no-shift order history.

## Compatibility and constraints

- Preserve existing route paths, successful JSON fields, order/payment statuses, and receipt-posting behavior.
- Do not add dependencies or alter existing database schemas unless implementation proves it necessary; any migration must be backward-compatible with existing installations.
- Preserve the user's existing edits in `OrderController.php` and `routes/api.php`.
- Do not change deployment targets or container behavior as part of this application-level reliability change. Report packaging inconsistencies separately rather than silently changing how the project is installed.

## Acceptance checks

- `php artisan route:list` completes successfully.
- Changed PHP files pass `php -l`.
- Focused automated tests pass using an isolated test database; no production or developer database is reset.
- A successful payment consumes FIFO batches and decreases material quantity once; failed payment or insufficient stock leaves payment, order status, batch balances, material balances, and ledger unchanged.
- Creating a material receipt changes stock exactly once.
- Order history works when no shift has yet been created.
- A final code review checks the diff against this specification and calls out any unmet item.
