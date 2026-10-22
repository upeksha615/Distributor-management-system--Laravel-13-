# Distributor System – Implementation Plan (Sri Lanka spec)

Status legend: ✅ exists and kept · 🔧 exists, will be changed · ➕ new · ⏸ deferred / needs external credentials

## Phase 1 – Inspection (done 2026-10-15)

Stack: Laravel 13.36, PHP 8.5 (XAMPP's PHP 8.0 cannot run it; served with `php artisan serve`), MariaDB 10.4 (XAMPP),
Blade + Tailwind 4 + Alpine.js, OpenSpout (Excel/CSV), Dompdf (PDF), picqer barcode.
32 models, ~155 routes, service classes for stock, sales, purchasing, payments, payables, quotations.
No automated tests yet.

### Gap analysis against the specification

| Area | Today | Plan |
|---|---|---|
| Roles & permissions | 4 fixed roles (`users.role`), 40 hard-coded checks | ➕ roles / permissions / role_permission tables, Gate-based checks, role editor (Phase A) |
| Company settings | ✅ name, address, tax, rules | 🔧 + logo, numbering prefixes, approval limits, cheque recognition policy, upload limits |
| Banks | free-text bank names | ➕ bank & branch master (CBSL licensed banks seeded), company bank accounts (Phase B) |
| Dealer payments | 🔧 recorded = paid immediately | 🔧 lifecycle Draft → Submitted → Pending verification → Under review → Verified / Rejected → Reversed; only **verified** money settles invoices; attachments; duplicate detection; segregation of duties (Phase C) |
| Payment slip inbox | – | ➕ manual upload now; email (IMAP/API) and WhatsApp Business webhook ⏸ until credentials exist (Phase C / J) |
| Office Admin workspace | – | ➕ dedicated dashboard + verification workspace (Phase C) |
| Cheques | 🔧 in hand / deposited / cleared / returned on payments | 🔧 status history, receiving bank account, cheque image, bank charges, duplicate rule, recognition policy (default: counts only when **cleared**) (Phase C) |
| Credit notes | 🔧 created and posted at once | 🔧 Draft → Submitted → Pending approval → Approved / Rejected → Posted → Reversed; approval limits; stock back only after inspection (Phase D) |
| Dealer ledger | derived from invoices/payments | ➕ ledger entries + opening balances, statement from ledger (Phase E) |
| Dealer portal | – | ➕ dealer login: own invoices, statement, submit slip, orders, return requests (Phase F) |
| Delivery | – | ➕ picking, delivery notes, drivers, vehicles, POD, failed/returned (Phase G) |
| Reserved stock | – | ➕ reserve on order confirmation (Phase G) |
| Purchasing | ✅ PO → GRN, supplier bills/payments | 🔧 PO approval, requisitions, supplier quotations, purchase returns/debit notes, outgoing cheques (Phase H) |
| Bank reconciliation | – | ➕ statement import (CSV/Excel), matching, differences (Phase I) |
| Notifications | ✅ email built: invoices, dealer payment notices, overdue reminders, daily summary, email log (delivers once SMTP is set) | WhatsApp ⏸ needs Meta Business credentials (Phase J) |
| Security | ✅ hashing, CSRF, throttled login, audit log, password reset by email, private file storage, scheduler (backups removed – done by the server team) | ➕ session expiry policy |
| Stock control | ✅ ledger, adjustments, transfers, counts via adjust | 🔧 adjustment approval, stock count sessions; batches/expiry only if needed for lubricants (Phase K) |
| Tests | – | ➕ PHPUnit feature tests per phase on a separate `distributor_test` database |

## Progress

| Phase | Status | Tests |
|---|---|---|
| A – Roles & permissions, dealer isolation, export permission | ✅ done 2026-10-16 | `AccessControlTest`, `SmokeTest` |
| B – Bank & branch master (CBSL list), company bank accounts, approval-limit / cheque-policy settings | ✅ done 2026-10-16 | `BankTest` |
| C – Payment verification lifecycle, duplicates, private slips, slip inbox (manual), verification queue, cheque workflow | ✅ done 2026-10-16 | `PaymentVerificationTest` |
| Catalogue: Category → Brand → Product, product origin / tax % / discount % / picture, per-line tax, rep stock colours, upcoming shipments | ✅ done 2026-10-16 | `CatalogueAndShipmentTest` |
| D – Credit note approval workflow | next | |
| E – Dealer ledger & opening balances | | |
| F – Dealer portal | | |
| G – Delivery management & reserved stock | | |
| H – Purchasing approvals, requisitions, purchase returns, outgoing cheques | | |
| I – Bank reconciliation | | |
| J – Email / WhatsApp integration, notifications, password reset, backups | email, password reset, scheduled tasks ✅ (backup feature later removed on request) (15 Oct 2026); inbound email slips + WhatsApp need credentials | 14 tests |
| K – Stock adjustment approval, stock counts | | |

Run the tests: `php artisan test` (uses the separate `distributor_test` database).

Data migration note (Phase C): payments recorded before 16 Oct 2026 were marked *verified* (they had been accepted under the
old process). Cheques among them settle invoices only once cleared, so invoice balances were recalculated.

## Roles (seeded, editable)

Super Admin · General Manager · Office Admin · Accountant · Finance Manager · Inventory Manager · Purchasing Officer ·
Sales Representative · Delivery Officer · Warehouse Staff · Auditor · Dealer (portal)

Existing users are migrated: admin → Super Admin, manager → General Manager, sales → Sales Representative, store → Inventory Manager.

## Financial model decisions

1. **Verified money only.** Invoice `paid_amount` and dealer balances count only allocations of payments with status `verified`.
   Pending / submitted / under-review payments are shown separately as "awaiting verification".
2. **Cheques** are recorded as payments with method `cheque`. Policy setting `cheque_recognition`:
   `on_clearance` (default) – the cheque settles invoices only when cleared; `on_verification` – when the accountant verifies receipt.
3. **Segregation of duties**: the user who entered a payment cannot verify it (setting, default on). Verification above the
   configured limit needs the `payments.verify_above_limit` permission.
4. **Reversal, not deletion.** Verified payments and posted credit notes are reversed with a reason; nothing financial is deleted.
5. **Duplicates** (same dealer + reference, or same amount + date + receiving account, or same bank + cheque number) raise a
   warning that must be reviewed; they are never silently discarded.
6. All postings run in DB transactions and write the audit log.
