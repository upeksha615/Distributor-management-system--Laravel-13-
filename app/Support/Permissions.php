<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The permission catalogue and the default role → permission matrix.
 * Roles and their permissions live in the database (editable by the Super Admin);
 * sync() adds new permissions / system roles without overwriting edited assignments.
 */
class Permissions
{
    /** group => [permission key => description] */
    public const CATALOG = [
        'General' => [
            'dashboard.view' => 'View the business dashboard',
            'reports.export' => 'Export lists and reports (Excel / CSV / PDF)',
        ],
        'Products & stock' => [
            'products.view' => 'View products and stock availability (colour only)',
            'stock.view_qty' => 'See exact stock and shipment quantities',
            'products.manage' => 'Create and edit products, part numbers, fitment, categories, brands',
            'products.view_cost' => 'See cost prices and margins',
            'stock.adjust' => 'Request stock adjustments / counts',
            'stock.adjust.approve' => 'Approve stock adjustments',
            'stock.transfer' => 'Transfer stock between warehouses',
            'warehouses.manage' => 'Manage warehouses',
            'imports.manage' => 'Import products, suppliers and stock from files',
        ],
        'Purchasing' => [
            'suppliers.view' => 'View suppliers',
            'suppliers.manage' => 'Create and edit suppliers',
            'purchases.manage' => 'Create purchase orders',
            'purchases.approve' => 'Approve / send purchase orders',
            'purchases.receive' => 'Receive goods (GRN)',
            'shipments.view' => 'See upcoming shipments',
            'shipments.manage' => 'Upload and receive upcoming shipments',
        ],
        'Dealers & sales' => [
            'customers.view' => 'View assigned dealers',
            'customers.view_all' => 'View all dealers (not only assigned)',
            'customers.manage' => 'Create and edit dealers',
            'customers.credit' => 'Change credit limits and holds',
            'quotations.manage' => 'Create quotations',
            'sales_orders.manage' => 'Create and edit sales orders',
            'invoices.view' => 'View invoices',
            'invoices.create' => 'Create invoices',
            'invoices.cancel' => 'Cancel unpaid invoices',
            'invoices.override_credit' => 'Override credit limit / hold',
            'invoices.email' => 'Email invoices to dealers',
            'field.rep' => 'Use the rep dashboard (dealer visits)',
            'field.monitor' => 'Monitor reps in the field',
        ],
        'Credit notes' => [
            'credit_notes.view' => 'View credit notes',
            'credit_notes.request' => 'Create credit note requests',
            'credit_notes.approve' => 'Approve / reject credit notes within limit',
            'credit_notes.approve_above_limit' => 'Approve credit notes above the limit',
            'credit_notes.reverse' => 'Reverse posted credit notes',
        ],
        'Dealer payments' => [
            'payments.view' => 'View dealer payments and statements',
            'payments.record' => 'Record payments and upload slips (inbox)',
            'payments.verify' => 'Verify / reject payments against the bank',
            'payments.verify_above_limit' => 'Verify payments above the approval limit',
            'payments.allocate' => 'Allocate verified payments to invoices',
            'payments.reverse' => 'Reverse verified payments',
            'cheques.manage' => 'Deposit cheques',
            'cheques.clear' => 'Confirm cheque clearance / returns',
        ],
        'Accounts' => [
            'payables.view' => 'View supplier bills and payments',
            'payables.manage' => 'Enter supplier bills and payments',
            'expenses.manage' => 'Enter expenses',
            'banks.manage' => 'Maintain bank & branch master and company bank accounts',
            'bank_accounts.view_numbers' => 'See full company bank account numbers',
            'reconciliation.manage' => 'Bank reconciliation',
            'reports.finance' => 'Financial reports (P&L, daily summary, ageing)',
            'reports.stock' => 'Stock valuation reports',
        ],
        'Delivery' => [
            'deliveries.manage' => 'Plan deliveries, assign drivers',
            'deliveries.pick' => 'Picking and packing tasks',
            'deliveries.drive' => 'View own deliveries and record proof of delivery',
        ],
        'Administration' => [
            'users.manage' => 'Manage user accounts',
            'roles.manage' => 'Manage roles and permissions',
            'settings.manage' => 'Company settings, numbering, approval limits',
            'audit.view' => 'View the audit log',
            'system.manage' => 'Scheduled tasks and email log',
            'notices.manage' => 'Post dashboard notices and maintain the holiday calendar',
            'notifications.daily_summary' => 'Receive the daily summary email',
        ],
        'Dealer portal' => [
            'portal.access' => 'Dealer portal (own account only)',
        ],
    ];

    /** slug => [name, description, permissions ('*' = everything)] */
    public const DEFAULT_ROLES = [
        'super_admin' => ['Super Admin', 'Full access, users, roles and settings.', '*'],
        'general_manager' => ['General Manager', 'Runs the business; approves purchases, discounts, credit notes.', [
            'dashboard.view', 'reports.export', 'products.view', 'stock.view_qty', 'products.manage', 'products.view_cost', 'stock.adjust', 'stock.adjust.approve',
            'stock.transfer', 'warehouses.manage', 'imports.manage', 'suppliers.view', 'suppliers.manage', 'purchases.manage', 'purchases.approve',
            'purchases.receive', 'shipments.view', 'shipments.manage', 'customers.view', 'customers.view_all', 'customers.manage', 'customers.credit', 'quotations.manage',
            'sales_orders.manage', 'invoices.view', 'invoices.create', 'invoices.cancel', 'invoices.override_credit', 'field.monitor',
            'credit_notes.view', 'credit_notes.request', 'credit_notes.approve', 'payments.view', 'payables.view', 'reports.finance',
            'reports.stock', 'deliveries.manage', 'audit.view', 'invoices.email', 'notifications.daily_summary', 'notices.manage',
        ]],
        'office_admin' => ['Office Admin', 'Registers dealer payments and slips, prepares them for verification.', [
            'dashboard.view', 'reports.export', 'products.view', 'stock.view_qty', 'customers.view', 'customers.view_all', 'invoices.view', 'credit_notes.view',
            'credit_notes.request', 'payments.view', 'payments.record', 'cheques.manage', 'invoices.email', 'notices.manage',
        ]],
        'accountant' => ['Accountant', 'Verifies payments against the bank, allocations, cheques, payables, reconciliation.', [
            'dashboard.view', 'reports.export', 'products.view', 'stock.view_qty', 'products.view_cost', 'customers.view', 'customers.view_all', 'invoices.view',
            'credit_notes.view', 'credit_notes.approve', 'payments.view', 'payments.record', 'payments.verify', 'payments.allocate',
            'cheques.manage', 'cheques.clear', 'payables.view', 'payables.manage', 'expenses.manage', 'banks.manage',
            'bank_accounts.view_numbers', 'reconciliation.manage', 'reports.finance', 'reports.stock', 'suppliers.view', 'invoices.email',
        ]],
        'finance_manager' => ['Finance Manager', 'Approves high-value items, reversals and write-offs.', [
            'dashboard.view', 'reports.export', 'products.view', 'stock.view_qty', 'products.view_cost', 'customers.view', 'customers.view_all', 'customers.credit',
            'invoices.view', 'invoices.override_credit', 'credit_notes.view', 'credit_notes.approve', 'credit_notes.approve_above_limit',
            'credit_notes.reverse', 'payments.view', 'payments.verify', 'payments.verify_above_limit', 'payments.allocate', 'payments.reverse',
            'cheques.manage', 'cheques.clear', 'payables.view', 'payables.manage', 'expenses.manage', 'banks.manage',
            'bank_accounts.view_numbers', 'reconciliation.manage', 'reports.finance', 'reports.stock', 'suppliers.view', 'audit.view',
            'invoices.email', 'notifications.daily_summary',
        ]],
        'inventory_manager' => ['Inventory Manager', 'Products, stock, receiving, transfers, adjustments.', [
            'dashboard.view', 'reports.export', 'products.view', 'stock.view_qty', 'products.manage', 'products.view_cost', 'stock.adjust', 'stock.adjust.approve',
            'stock.transfer', 'warehouses.manage', 'imports.manage', 'suppliers.view', 'purchases.receive', 'shipments.view', 'shipments.manage', 'reports.stock', 'deliveries.manage',
        ]],
        'purchasing_officer' => ['Purchasing Officer', 'Suppliers and purchase orders.', [
            'dashboard.view', 'reports.export', 'products.view', 'stock.view_qty', 'products.view_cost', 'suppliers.view', 'suppliers.manage', 'purchases.manage',
            'shipments.view', 'shipments.manage', 'payables.view',
        ]],
        'sales_rep' => ['Sales Representative', 'Visits dealers, quotations, orders; sees assigned dealers.', [
            'dashboard.view', 'products.view', 'shipments.view', 'customers.view', 'customers.manage', 'quotations.manage', 'sales_orders.manage', 'invoices.view',
            'field.rep', 'credit_notes.view', 'credit_notes.request', 'payments.view', 'payments.record',
        ]],
        'delivery_officer' => ['Delivery Officer / Driver', 'Own deliveries and proof of delivery.', ['deliveries.drive']],
        'warehouse_staff' => ['Warehouse Staff', 'Picking, packing and receiving.', ['products.view', 'stock.view_qty', 'deliveries.pick', 'purchases.receive', 'shipments.view']],
        'auditor' => ['Auditor', 'Read-only access to financial and stock records.', [
            'dashboard.view', 'reports.export', 'products.view', 'stock.view_qty', 'products.view_cost', 'suppliers.view', 'customers.view', 'customers.view_all',
            'invoices.view', 'credit_notes.view', 'payments.view', 'payables.view', 'reports.finance', 'reports.stock', 'audit.view',
        ]],
        'dealer' => ['Dealer (portal)', 'External dealer login: own account only.', ['portal.access']],
    ];

    /** Old fixed roles → new role slugs. */
    public const LEGACY = ['admin' => 'super_admin', 'manager' => 'general_manager', 'sales' => 'sales_rep', 'store' => 'inventory_manager'];

    /**
     * Give newly added permission keys to the roles whose default list includes them
     * (existing roles are otherwise left exactly as the Super Admin configured them).
     */
    public static function grantDefaults(array $keys): void
    {
        $permIds = DB::table('permissions')->whereIn('key', $keys)->pluck('id', 'key');
        foreach (self::DEFAULT_ROLES as $slug => [, , $perms]) {
            if ($perms === '*') {
                continue;
            }
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            foreach (array_intersect($keys, $perms) as $key) {
                if ($roleId && isset($permIds[$key])) {
                    DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permIds[$key]]);
                }
            }
        }
    }

    public static function all(): array
    {
        return collect(self::CATALOG)->flatMap(fn ($perms) => $perms)->all();
    }

    /**
     * Create missing permissions and roles. New system roles get their default permissions;
     * existing roles are left as the Super Admin configured them.
     */
    public static function sync(): void
    {
        $now = now();
        foreach (self::CATALOG as $group => $perms) {
            foreach ($perms as $key => $label) {
                DB::table('permissions')->updateOrInsert(['key' => $key], ['group' => $group, 'label' => $label, 'updated_at' => $now]);
            }
        }
        $permIds = DB::table('permissions')->pluck('id', 'key');

        foreach (self::DEFAULT_ROLES as $slug => [$name, $description, $perms]) {
            if (DB::table('roles')->where('slug', $slug)->exists()) {
                continue;
            }
            $roleId = DB::table('roles')->insertGetId([
                'slug' => $slug, 'name' => $name, 'description' => $description, 'is_system' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($perms !== '*') {
                DB::table('permission_role')->insert(collect($perms)->map(fn ($p) => ['role_id' => $roleId, 'permission_id' => $permIds[$p]])->all());
            }
        }
    }
}
