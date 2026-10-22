# Distributor System

Laravel 13 + MySQL (XAMPP) system for a parts distributor: inventory, purchasing, sales/invoicing, payments and debtors.

## Requirements

- PHP **8.3+** (this machine uses `C:\Program Files\php-8.5.8`). XAMPP's bundled PHP 8.0 / Apache **cannot** run Laravel 13.
- XAMPP MySQL/MariaDB running (database `distributor`).
- Node.js (only to rebuild CSS/JS after changing views).

## Run it

```bash
cd C:\xampp\htdocs\distributor
php artisan serve
```

Open http://localhost:8000 (if port 8000 is busy, `php artisan serve --port=8001`).

## First-time setup / reset

```bash
php artisan migrate:fresh --seed                 # empty system: admin user + "Main Store" warehouse
php artisan db:seed --class=DemoDataSeeder       # optional sample products, customers, invoices, payments
npm install && npm run build                     # after editing views, CSS or JS
```

Login accounts are created in `database/seeders/DatabaseSeeder.php` (admin) and `DemoDataSeeder.php`
(manager, sales rep, storekeeper). **Change these passwords (Setup → Users) before real use.**

## Settings (`.env`)

| Key | Meaning |
|---|---|
| `COMPANY_NAME`, `COMPANY_ADDRESS`, `COMPANY_PHONE`, `COMPANY_EMAIL`, `COMPANY_TAX_NUMBER` | Printed on invoices, POs, receipts, statements |
| `CURRENCY_SYMBOL` | e.g. `Rs.` |
| `DEFAULT_TAX_RATE` | Default tax % on new orders/invoices (editable per document) |
| `ALLOW_NEGATIVE_STOCK` | `false` blocks invoices that would take stock below zero |
| `ENFORCE_CREDIT_LIMIT` | `true` blocks invoices over a customer's credit limit unless a manager overrides |

Run `php artisan config:clear` after changing `.env`. Admins can also change company details, tax rate and stock / credit rules in **Setup → Settings** (these override `.env`).

## Email, password reset and scheduled tasks

- **Outgoing email:** enter the SMTP server in **Setup → Email settings** (password stored encrypted; overrides `.env`), or use the `MAIL_*` settings in `.env`. With `MAIL_MAILER=log` (the default) nothing is delivered – messages
  are only written to `storage/logs/laravel.log`, and the system says so. For real delivery set `MAIL_MAILER=smtp` plus host,
  port, username, password and `MAIL_FROM_ADDRESS`, then use **Setup → Scheduled tasks → Send test**.
  Every email (and whether it was sent, logged only, or failed) is listed in **Setup → Email log**.
- **What is emailed:** password reset links; invoices with PDF (Invoice → *Email invoice*); dealer notices when a payment is
  verified / rejected or a cheque is returned (Settings switch, only to dealers with an email); weekly overdue reminders to
  dealers (off by default); a daily summary to users whose role has *Receive the daily summary email*.
- **Password reset:** *Forgot your password?* on the login page, or **Users → Edit → Send password reset link**. Links expire after
  60 minutes and work once; resetting signs the user out of other sessions.
- **Scheduler:** the automatic tasks only run while Laravel's scheduler runs:
  - Linux: cron `* * * * * cd /var/www/distributor && php artisan schedule:run >> /dev/null 2>&1`
  - Windows: Task Scheduler every minute: `php C:\xampp\htdocs\distributor\artisan schedule:run`
  - Development: `php artisan schedule:work`

  | Task | When |
  |---|---|
  | `summary:daily` | daily 07:30 |
  | `reminders:overdue` | Mondays 08:30 (if enabled) |
  | `visits:close-stale` | daily 00:15 – closes rep visits left open |
  | `auth:clear-resets` | daily 03:00 – removes expired reset links |

  The Scheduled tasks page shows whether the scheduler is running, the next run of each task and its last result.

## Modules

- **Inventory**: products, brands, categories, warehouses, stock per warehouse, stock adjustments (stock take / opening stock), transfers, full movement log, low-stock report, stock valuation.
- **Purchasing**: suppliers → purchase order (draft → ordered) → goods received notes (partial receiving supported). Receiving adds stock and updates product cost price.
- **Sales**: customers with credit limit, terms and sales rep; sales orders (no stock effect) → invoice (deducts stock, checks credit limit/hold); credit notes for returns (optional restock); invoice cancel (unpaid only, returns stock).
- **Payments & debtors**: cash / cheque / bank transfer / card; auto-allocate oldest-first, manual allocation, or keep on account; returned (bounced) cheques reopen invoices; customer statement; aged debtors (not due / 1-30 / 31-60 / 61-90 / 90+).
- **Spare-parts data**: OEM part number, barcode, rack/bin location, cross-reference numbers (alternate / other brands), vehicle fitment (make, model, years, engine). Search finds parts by any number (with or without dashes), name words, vehicle, engine code or bin. Retail, wholesale (dealer) and minimum prices; customers are priced by their price level.
- **Field sales (reps)**: rep dashboard (phone friendly) listing the rep's dealers with balance and last visit; check in at a dealer (time + GPS; first visit saves the dealer's location, later check-ins more than 500 m away are flagged); visit page with balance, overdue, credit, usual products; take order (goes to the office as a sales order marked "Rep"); collect cash/cheque/bank payment; check out with outcome, notes and next visit date. Admin **Field activity** page: reps at dealers right now (live), per-rep day totals, rep orders waiting to be invoiced, visit log with export. Admins/managers get live pop-up alerts for check-ins, orders and collections. *Phones only share GPS over HTTPS (or localhost), so put the system on an HTTPS address for reps.*
- **Quotations**: quote → convert to sales order or invoice; validity / expiry; PDF.
- **Supplier payables**: goods received automatically create supplier bills; manual bills for freight, duty, clearing, rent; supplier payments with allocation; aged creditors; supplier statements; payment vouchers.
- **Cheque register**: customer cheques in hand → deposited → cleared / returned, post-dated cheques due to deposit.
- **Expenses**: categories, expense entry, expense report.
- **Reports**: daily summary, profit & loss, sales & margin by customer / product / brand / rep / day, aged debtors and creditors, expense report, stock valuation, low stock / reorder.
- **Control**: audit log (cancellations, price changes, stock adjustments, returned cheques, user changes); cost prices hidden from the sales role; barcode label printing (A4 sheets or label roll).

## Import / export

- **Export**: every list and report has *Export → Excel / CSV / PDF*. It exports all rows matching the current filters (not just the visible page). Numbers stay numeric in Excel.
- **Document PDFs**: invoices, sales orders, POs, goods received notes, credit notes, payment receipts and customer statements have *Download PDF*.
- **Import** (Setup → Import data): products, customers, suppliers and stock quantities from `.xlsx` or `.csv`. Download the template first. Rows are matched by SKU / code (existing records are updated, new ones created). Every row is validated before anything is saved; if any row fails, nothing is imported and the problem rows are listed with their spreadsheet row numbers.
- Libraries: `openspout/openspout` (Excel/CSV), `dompdf/dompdf` (PDF). Code: `app/Support/Exporter.php`, `app/Support/SpreadsheetReader.php`, `app/Services/ImportService.php`, `app/Http/Controllers/DocumentPdfController.php`.

## Roles

| Role | Access |
|---|---|
| Administrator | Everything, including users, settings and audit log |
| Manager | Everything except users/settings; accounts, expenses, cheques, P&L; credit override, cancel invoices, return cheques, see all shifts |
| Sales | Customers, quotations, orders, invoices, credit notes, payments, rep dashboard (visits, orders, collections), aged debtors; view products/stock |
| Storekeeper | Products, stock, labels, warehouses, suppliers, purchase orders, goods received, low stock, imports |

## Code map

- `app/Services/StockService.php`: the only place stock changes (locks rows, logs every movement)
- `app/Services/PurchaseService.php`, `SalesService.php`, `PaymentService.php`, `PayablesService.php`, `QuotationService.php`: business rules, all in DB transactions
- `routes/modules/*.php`: routes for quotations, payables and expenses/cheques
- `app/Support/DocumentNumber.php`: numbering such as `INV-2026-00001`, `PO-…`, `GRN-…`, `CN-…`, `RCPT-…`
- `resources/views/partials/line-items.blade.php` + `resources/js/app.js`: product search / line editor
