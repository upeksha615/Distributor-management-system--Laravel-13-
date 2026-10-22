<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\Holiday;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Shipment;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\MailService;
use App\Support\ScheduledTasks;
use Illuminate\Support\Facades\Route;

/**
 * Start page. Deliberately shows no company figures (no sales, money, balances, dealer names or stock quantities):
 * only the user's own shortcuts, how many tasks are waiting, notices, holidays and (for admins) system status.
 */
class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();

        // Reps, drivers and others without the office dashboard go straight to their own work page.
        if (! $user->can('dashboard.view') || ! $user->canSeeAllDealers()) {
            foreach (['field.rep' => 'rep.index', 'payments.record' => 'payment-inbox.index', 'deliveries.drive' => 'deliveries.mine',
                'deliveries.pick' => 'deliveries.picking', 'products.view' => 'products.index'] as $permission => $route) {
                if ($user->can($permission) && Route::has($route)) {
                    return redirect()->route($route);
                }
            }
            abort(403, 'Your role has no start page yet. Ask an administrator to assign permissions.');
        }

        $month = today()->startOfMonth();

        return view('dashboard', [
            'actions' => $this->actions($user),
            'tasks' => $this->tasks($user),
            'notices' => Notice::current()->orderByRaw("level = 'important' desc")->latest('starts_on')->limit(6)->get(),
            'month' => $month,
            'monthHolidays' => Holiday::whereBetween('date', [$month, $month->copy()->endOfMonth()])->orderBy('date')->get(),
            'upcomingHolidays' => Holiday::where('date', '>=', today())->orderBy('date')->limit(4)->get(),
            'system' => $user->can('system.manage') ? $this->systemStatus() : null,
        ]);
    }

    /** Shortcut buttons for the jobs this user is allowed to do. */
    private function actions(User $user): array
    {
        $all = [
            ['New invoice', 'invoices.create', 'invoices.create', 'indigo', 'M19.5 14.25v-2.63a3.38 3.38 0 0 0-3.38-3.37h-1.5A1.13 1.13 0 0 1 13.5 7.13v-1.5a3.38 3.38 0 0 0-3.38-3.38H8.25m3.75 9v6m3-3H9m1.5-12H5.63c-.63 0-1.13.5-1.13 1.13v17.25c0 .62.5 1.12 1.13 1.12h12.75c.62 0 1.12-.5 1.12-1.12V11.25a9 9 0 0 0-9-9Z'],
            ['Sales order', 'sales-orders.create', 'sales_orders.manage', 'violet', 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.36-1.99 1.26 12c.07.66-.45 1.24-1.12 1.24H4.25a1.13 1.13 0 0 1-1.12-1.24l1.26-12A1.13 1.13 0 0 1 5.51 7.5h12.98c.58 0 1.06.43 1.12 1.01Z'],
            ['Quotation', 'quotations.create', 'quotations.manage', 'sky', 'M16.86 4.49 18.55 2.8a1.88 1.88 0 1 1 2.65 2.65L10.58 16.07a4.5 4.5 0 0 1-1.9 1.13L6 18l.8-2.68a4.5 4.5 0 0 1 1.13-1.9l8.93-8.93Zm0 0L19.5 7.13M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10'],
            ['Record payment', 'payments.create', 'payments.record', 'emerald', 'M2.25 18.75a60 60 0 0 1 15.8 2.1c.73.2 1.45-.34 1.45-1.1V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.38c0-.62.5-1.12 1.13-1.12H20.25M2.25 6v9m18-10.5v.75c0 .41.34.75.75.75h.75m-1.5-1.5h.38c.62 0 1.12.5 1.12 1.13v9.75c0 .62-.5 1.12-1.12 1.12h-.38m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.38a1.13 1.13 0 0 1-1.12-1.13V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'],
            ['Upload payment slip', 'payment-inbox.create', 'payments.record', 'teal', 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5'],
            ['Verify payments', 'payment-verification.index', 'payments.verify', 'amber', 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            ['Check stock', 'products.index', 'products.view', 'sky', 'm20.25 7.5-.63 10.63a2.25 2.25 0 0 1-2.24 2.12H6.62a2.25 2.25 0 0 1-2.24-2.12L3.75 7.5M10 11.25h4M3.38 7.5h17.25c.62 0 1.12-.5 1.12-1.13v-1.5c0-.62-.5-1.12-1.12-1.12H3.38c-.63 0-1.13.5-1.13 1.12v1.5c0 .63.5 1.13 1.13 1.13Z'],
            ['Upload shipment', 'shipments.create', 'shipments.manage', 'teal', 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.38A1.13 1.13 0 0 1 2.25 17.63V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.13c.62 0 1.13-.5 1.12-1.13a17.9 17.9 0 0 0-3.21-9.64 2.06 2.06 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.18V6.11c0-.56-.42-1.05-.98-1.11a48.4 48.4 0 0 0-10.04 0c-.56.06-.98.55-.98 1.11v8.14m12 0h-12'],
            ['Purchase order', 'purchase-orders.create', 'purchases.manage', 'violet', 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.11c0-1.13-.84-2.08-1.96-2.18a48.4 48.4 0 0 0-1.12-.08m-5.8 0a2.25 2.25 0 0 0-.1.63c0 .41.34.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.63m-5.8 0A2.25 2.25 0 0 1 13.5 2.25H15c1.01 0 1.87.67 2.15 1.6m-5.8 0c-.38.02-.75.05-1.12.08C9.1 4.02 8.25 4.98 8.25 6.11V8.25m0 0H4.88c-.62 0-1.13.5-1.13 1.13v11.25c0 .62.5 1.12 1.13 1.12h9.75c.62 0 1.12-.5 1.12-1.12V9.38c0-.62-.5-1.13-1.12-1.13H8.25Z'],
            ['Adjust stock', 'stock.adjust', 'stock.adjust', 'rose', 'M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75'],
            ['New dealer', 'customers.create', 'customers.manage', 'indigo', 'M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.13a3.38 3.38 0 1 1-6.75 0 3.38 3.38 0 0 1 6.75 0ZM3 19.24v-.12a6.38 6.38 0 0 1 12.75 0v.12A12.32 12.32 0 0 1 9.37 21c-2.33 0-4.5-.65-6.37-1.76Z'],
            ['Reports', 'reports.daily', 'reports.finance', 'slate', 'M3 13.13C3 12.5 3.5 12 4.13 12h2.25c.62 0 1.12.5 1.12 1.13v6.75C7.5 20.5 7 21 6.38 21H4.13A1.13 1.13 0 0 1 3 19.88v-6.75ZM9.75 8.63c0-.63.5-1.13 1.13-1.13h2.25c.62 0 1.12.5 1.12 1.13v11.25c0 .62-.5 1.12-1.12 1.12h-2.25a1.13 1.13 0 0 1-1.13-1.12V8.63ZM16.5 4.13c0-.63.5-1.13 1.13-1.13h2.25C20.5 3 21 3.5 21 4.13v15.75c0 .62-.5 1.12-1.12 1.12h-2.25a1.13 1.13 0 0 1-1.13-1.12V4.13Z'],
        ];

        return collect($all)
            ->filter(fn ($a) => Route::has($a[1]) && $user->can($a[2]))
            ->map(fn ($a) => ['label' => $a[0], 'href' => route($a[1]), 'tone' => $a[3], 'icon' => $a[4]])
            ->take(8)->values()->all();
    }

    /** How many items are waiting, per job the user can do. Counts only – no amounts or names. */
    private function tasks(User $user): array
    {
        $link = fn ($name, $params = []) => Route::has($name) ? route($name, $params) : null;
        $tasks = [];
        if ($user->can('payments.verify')) {
            $tasks[] = ['Payments to verify', Payment::awaitingVerification()->count(), $link('payment-verification.index'), 'amber'];
        }
        if ($user->can('payments.record')) {
            $tasks[] = ['Payment slips in the inbox', PaymentSubmission::whereIn('status', ['new', 'matched', 'info_requested'])->count(), $link('payment-inbox.index'), 'teal'];
        }
        if ($user->can('cheques.manage')) {
            $tasks[] = ['Cheques ready to deposit', Payment::where('method', 'cheque')->where('cheque_status', 'received')
                ->whereNotIn('status', ['rejected', 'reversed'])->where('cheque_date', '<=', today())->count(), $link('cheques.index'), 'amber'];
        }
        if ($user->can('cheques.clear')) {
            $tasks[] = ['Cheques waiting for clearance', Payment::where('cheque_status', 'deposited')->count(), $link('cheques.index'), 'sky'];
        }
        if ($user->can('sales_orders.manage') || $user->can('invoices.create')) {
            $tasks[] = ['Rep orders to invoice', SalesOrder::where('source', 'rep')->where('status', 'pending')->count(), $link('sales-orders.index', ['source' => 'rep', 'status' => 'pending']), 'violet'];
            $tasks[] = ['Sales orders pending', SalesOrder::where('status', 'pending')->count(), $link('sales-orders.index', ['status' => 'pending']), 'violet'];
        }
        if ($user->can('stock.view_qty') && $user->can('reports.stock')) {
            $tasks[] = ['Products to reorder', Product::where('is_active', true)->where('reorder_level', '>', 0)->withTotalStock()->get()
                ->filter(fn ($p) => (float) $p->total_stock <= $p->reorder_level)->count(), $link('reports.low-stock'), 'rose'];
        }
        if ($user->can('shipments.manage')) {
            $tasks[] = ['Shipments past expected date', Shipment::whereIn('status', ['pending', 'partial'])->where('expected_date', '<', today())->count(), $link('shipments.index'), 'rose'];
        }
        if ($user->can('payables.manage')) {
            $tasks[] = ['Supplier bills due within 7 days', SupplierBill::open()->where('due_date', '<=', today()->addDays(7))->count(), $link('supplier-bills.index'), 'rose'];
        }

        return array_map(fn ($t) => ['label' => $t[0], 'count' => $t[1], 'href' => $t[2], 'tone' => $t[3]], $tasks);
    }

    private function systemStatus(): array
    {
        $tick = ScheduledTasks::lastTick();

        return [
            'scheduler' => $tick && $tick->gt(now()->subMinutes(5)),
            'mail' => MailService::delivers(),
            'failed_emails' => EmailLog::where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }
}
