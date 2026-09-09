# Dahab ERP — Integrated Improvement Sprints

## Sprint 1 — Runtime and autoload stability

- Added Composer classmap exclusions for the legacy `app/files` archive and misplaced duplicate classes.
- Reduced eligible duplicate fully-qualified class names from 224 to zero without deleting source backups.
- Separated the active `production_orders` workflow into `ProductionOrderService` while preserving the newer batch service.
- Added compatibility APIs to `ProductionContextService` and restored production model fields/statuses used by the active workflow.

## Sprint 2 — Inventory expiry

- Replaced the invalid scheduled command `inventory:scan-expiry` with the registered `inventory:check-expiry` command.
- Centralized Laravel 12 scheduling in `bootstrap/app.php`.
- Added the missing manual scan controller action and protected it with `inventory.expiry-alerts.run`.
- Added the missing print action.
- Enforced branch scoping for non-admin users.
- Kept expiry statistics in non-overlapping bands: 0–7, 8–30 and 31–60 days.

## Sprint 3 — Product supplier visibility

- Loaded and displayed supplier relationships from the product profile.
- Restricted purchase-price visibility to authorized users.
- Displayed preferred/alternative/inactive suppliers, MOQ, lead time, supplier SKU and normalized stock-unit cost.

## Sprint 4 — Supplier purchase units and immutable snapshots

- Added supplier-specific purchase units, package descriptions and conversion factors.
- Added immutable supplier/product/unit/conversion snapshots to purchase-order lines.
- Added purchase-unit snapshots to supplier price history.
- Converted accepted receipt quantities to inventory base units.
- Converted returned purchase quantities back to the exact same inventory base units.
- Preserved all existing records with conversion factor 1 and backfilled their base quantities.

## Sprint 5 — Supplier comparison UX

- Purchase-order product metadata now shows purchase unit, package, conversion factor, MOQ and lead time.
- Product supplier table shows cost per inventory unit and highlights the lowest cost inside each currency.
- Escaped supplier-entered metadata before inserting it into purchase-order HTML.

## Sprint 6 — Regression protection

- Added `PurchaseUnitConverterTest` for direct units, cartons and fractional packages.
- Added `AutoloadHygieneTest` to prevent duplicate classes from returning.
- Added a scheduler regression assertion for the expiry command.

## Sprint 7 — Workflow and database integrity

- Repaired every explicit route that referenced a missing controller method.
- Unified recipe route names with the production UI and connected approve/new-version actions to the implemented service methods.
- Removed accidental loading of `routes/console.php` during web requests.
- Added the missing expiry print and manual scan actions, including branch scoping and permission protection.
- Canonicalized recipe columns additively so fresh and upgraded databases support the active recipe workflow despite the legacy overlapping migrations.
- Removed the competing legacy expiry-delivery schema and aligned the model with the active scanner.
- Added the read-only `system:audit-logic` command for real-database consistency checks.
- Added `CriticalRouteIntegrityTest` to prevent routes from pointing at missing or non-public methods.

## Sprint 8 — Sales, collections, cash and payroll integrity

- Centralized order and special-cake collections in a transaction-safe payment service.
- Locked source documents during collection, verification and correction to prevent concurrent overpayment.
- Enforced payment-method branch availability, required references and verification proofs.
- Reserved pending-verification amounts and synchronized payment status after collection, correction and refund.
- Prevented cancelling collected orders until their money is refunded, restored confirmed-order inventory, and cancelled linked invoices safely.
- Replaced race-prone sales order, invoice, special-cake order and payroll period numbers with locked document sequences.
- Added idempotent sales-ledger posting for invoices, collections, corrections, refunds and cancellations.
- Corrected financial-period cash closing to use collections minus refunds and net expenses.
- Included customer-account receipts in financial-period collection totals and cashier closing totals.
- Prevented duplicate customer-payment verification and overlapping payroll periods.
- Locked payroll periods, items and payments while calculating, approving, paying, verifying, rejecting or voiding payroll.
- Derived payroll payable balances from the employee ledger to prevent repeated deductions and duplicate reversals.
- Expanded `system:audit-logic` with invoice, refund, cancelled-order, cash-session, financial-period and payroll consistency checks.

## Deployment

Back up the database first, then run:

```bash
composer dump-autoload -o
php artisan optimize:clear
php artisan migrate
php artisan test
php artisan schedule:list
php artisan system:audit-logic
```

Expected schedule entry:

```text
inventory:check-expiry    daily at 08:00
```

## Compatibility and rollback

- Existing supplier-product records retain conversion factor `1`.
- Existing purchase, receipt and return quantities are copied into their base-quantity snapshot fields unchanged.
- No historical procurement row is deleted or rewritten beyond the safe base-quantity backfill.
- The new migration has a complete `down()` implementation.

## Verification note

Static architecture checks were executed in the audit workspace. The workspace did not provide a PHP executable, so the Laravel/PHPUnit commands above must be executed on the target development machine before production deployment.
