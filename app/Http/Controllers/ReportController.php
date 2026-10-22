<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public const AGE_BUCKETS = ['current' => 'Not yet due', 'd30' => '1-30 days', 'd60' => '31-60 days', 'd90' => '61-90 days', 'd90plus' => 'Over 90 days'];

    /** Open invoice balances by customer, bucketed by days past the due date. */
    public function agedDebtors(Request $request)
    {
        $invoices = $this->dealerScope(Invoice::with('customer.salesRep'))
            ->open()
            ->when($request->sales_rep_id, fn ($q, $id) => $q->whereHas('customer', fn ($c) => $c->where('sales_rep_id', $id)))
            ->get();

        $rows = $invoices->groupBy('customer_id')->map(function ($group) {
            $row = ['customer' => $group->first()->customer, 'total' => 0, 'oldest' => 0] + array_fill_keys(array_keys(self::AGE_BUCKETS), 0);

            foreach ($group as $inv) {
                $days = $inv->daysOverdue();
                $bucket = match (true) {
                    $days <= 0 => 'current',
                    $days <= 30 => 'd30',
                    $days <= 60 => 'd60',
                    $days <= 90 => 'd90',
                    default => 'd90plus',
                };
                $row[$bucket] += $inv->balance();
                $row['total'] += $inv->balance();
                $row['oldest'] = max($row['oldest'], $days);
            }

            return $row;
        })->sortByDesc('total')->values();

        $totals = array_fill_keys(array_merge(array_keys(self::AGE_BUCKETS), ['total']), 0);
        foreach ($rows as $row) {
            foreach ($totals as $key => $_) {
                $totals[$key] += $row[$key];
            }
        }

        if ($format = $this->exportFormat($request)) {
            $rep = $request->sales_rep_id ? User::find($request->sales_rep_id)?->name : null;
            $columns = ['Code' => 'text', 'Customer' => 'text', 'Sales rep' => 'text', 'Credit limit' => 'money']
                + collect(self::AGE_BUCKETS)->mapWithKeys(fn ($label) => [$label => 'money'])->all()
                + ['Total' => 'money', 'Oldest (days)' => 'number'];

            return Exporter::download($format, 'aged-debtors', 'Aged debtors', $columns, $rows->map(fn ($r) => array_merge(
                [$r['customer']->code, $r['customer']->name, $r['customer']->salesRep?->name, $r['customer']->credit_limit],
                array_map(fn ($k) => $r[$k], array_keys(self::AGE_BUCKETS)),
                [$r['total'], $r['oldest']],
            )), 'As of '.today()->format('d/m/Y').($rep ? ' · Rep: '.$rep : ''),
                array_merge(['Total', null, null, null], array_map(fn ($k) => $totals[$k], array_keys(self::AGE_BUCKETS)), [$totals['total'], null]));
        }

        return view('reports.aged-debtors', [
            'rows' => $rows,
            'totals' => $totals,
            'buckets' => self::AGE_BUCKETS,
            'reps' => User::whereIn('role', ['sales', 'manager', 'admin'])->orderBy('name')->get(),
        ]);
    }

    /** Sales with cost and gross profit, grouped by customer, product, brand, rep or day. */
    public function sales(Request $request)
    {
        $from = $request->input('from', today()->startOfMonth()->toDateString());
        $to = $request->input('to', today()->toDateString());
        $group = in_array($request->group, ['customer', 'product', 'brand', 'rep', 'day']) ? $request->group : 'customer';

        [$key, $label] = match ($group) {
            'customer' => ['c.id', 'c.name'],
            'product' => ['p.id', "CONCAT(p.sku, ' - ', p.name)"],
            'brand' => ['b.id', "COALESCE(b.name, '(No brand)')"],
            'rep' => ['u.id', "COALESCE(u.name, '(No rep)')"],
            'day' => ['i.invoice_date', 'i.invoice_date'],
        };

        // Line totals are before header discount/tax; returns are netted out using returned_quantity.
        $rows = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->join('customers as c', 'c.id', '=', 'i.customer_id')
            ->join('products as p', 'p.id', '=', 'ii.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.sales_rep_id')
            ->where('i.status', '!=', 'cancelled')
            ->whereBetween('i.invoice_date', [$from, $to])
            ->groupBy(DB::raw($key), DB::raw($label))
            ->selectRaw("{$label} as label")
            ->selectRaw('SUM(ii.quantity - ii.returned_quantity) as qty')
            ->selectRaw('SUM(ii.line_total * (ii.quantity - ii.returned_quantity) / ii.quantity) as sales')
            ->selectRaw('SUM(ii.unit_cost * (ii.quantity - ii.returned_quantity)) as cost')
            ->selectRaw('COUNT(DISTINCT i.id) as invoices')
            ->orderByDesc($group === 'day' ? DB::raw('MIN(i.invoice_date)') : 'sales')
            ->get();

        if ($format = $this->exportFormat($request)) {
            $heading = ['customer' => 'Customer', 'product' => 'Product', 'brand' => 'Brand', 'rep' => 'Sales rep', 'day' => 'Date'][$group];
            $data = $rows->map(fn ($r) => [
                $group === 'day' ? Carbon::parse($r->label)->format('d/m/Y') : $r->label,
                $r->invoices, $r->qty, $r->sales, $r->cost, $r->sales - $r->cost,
                $r->sales > 0 ? round(($r->sales - $r->cost) / $r->sales * 100, 1) : null,
            ]);
            $sales = $rows->sum('sales');
            $cost = $rows->sum('cost');

            return Exporter::download($format, 'sales-report', 'Sales & gross profit by '.strtolower($heading), [
                $heading => 'text', 'Invoices' => 'number', 'Qty' => 'qty', 'Sales' => 'money', 'Cost' => 'money', 'Gross profit' => 'money', 'Margin %' => 'qty',
            ], $data, Carbon::parse($from)->format('d/m/Y').' to '.Carbon::parse($to)->format('d/m/Y'),
                ['Total', null, $rows->sum('qty'), $sales, $cost, $sales - $cost, $sales > 0 ? round(($sales - $cost) / $sales * 100, 1) : null]);
        }

        return view('reports.sales', compact('rows', 'from', 'to', 'group'));
    }

    public function stockValuation(Request $request)
    {
        $warehouses = Warehouse::orderBy('name')->get();

        $rows = DB::table('stock_levels as s')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->when($request->warehouse_id, fn ($q, $id) => $q->where('s.warehouse_id', $id))
            ->where('s.quantity', '!=', 0)
            ->groupBy('p.id', 'p.sku', 'p.name', 'b.name', 'p.cost_price', 'p.selling_price')
            ->select('p.id', 'p.sku', 'p.name', 'b.name as brand', 'p.cost_price', 'p.selling_price')
            ->selectRaw('SUM(s.quantity) as qty')
            ->orderBy('p.name')
            ->get();

        if ($format = $this->exportFormat($request)) {
            $warehouse = $request->warehouse_id ? $warehouses->firstWhere('id', $request->warehouse_id)?->name : 'All warehouses';
            $cost = $rows->sum(fn ($r) => $r->qty * $r->cost_price);
            $price = $rows->sum(fn ($r) => $r->qty * $r->selling_price);

            return Exporter::download($format, 'stock-valuation', 'Stock valuation', [
                'SKU' => 'text', 'Name' => 'text', 'Brand' => 'text', 'Qty' => 'qty', 'Unit cost' => 'money',
                'Selling price' => 'money', 'Value at cost' => 'money', 'Value at price' => 'money',
            ], $rows->map(fn ($r) => [
                $r->sku, $r->name, $r->brand, $r->qty, $r->cost_price, $r->selling_price, $r->qty * $r->cost_price, $r->qty * $r->selling_price,
            ]), $warehouse.' · as of '.today()->format('d/m/Y'), ['Total', null, null, $rows->sum('qty'), null, null, $cost, $price]);
        }

        return view('reports.stock-valuation', compact('rows', 'warehouses'));
    }

    public function lowStock(Request $request)
    {
        $products = Product::with(['brand'])
            ->where('is_active', true)
            ->where('reorder_level', '>', 0)
            ->withTotalStock()
            ->orderBy('name')
            ->get()
            ->filter(fn ($p) => (float) $p->total_stock <= $p->reorder_level)
            ->values();

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'low-stock', 'Low stock', [
                'SKU' => 'text', 'Name' => 'text', 'Brand' => 'text', 'In stock' => 'qty', 'Reorder level' => 'number',
                'Suggested order' => 'qty', 'Last cost' => 'money',
            ], $products->map(fn ($p) => [
                $p->sku, $p->name, $p->brand?->name, $p->total_stock ?? 0, $p->reorder_level,
                max(0, $p->reorder_level * 2 - (float) $p->total_stock), $p->cost_price,
            ]), 'As of '.today()->format('d/m/Y'));
        }

        return view('reports.low-stock', compact('products'));
    }
    /** Profit & loss for a period: net sales, cost of sales, stock write-offs and expenses. */
    public function profitLoss(Request $request)
    {
        $from = $request->input('from', today()->startOfMonth()->toDateString());
        $to = $request->input('to', today()->toDateString());

        $invoices = DB::table('invoices')->where('status', '!=', 'cancelled')->whereBetween('invoice_date', [$from, $to]);
        $grossSales = (float) (clone $invoices)->sum('subtotal');
        $discounts = (float) (clone $invoices)->sum('discount');
        $taxCollected = (float) (clone $invoices)->sum('tax');

        // Credit notes in the period, with tax removed in the same proportion as their invoice.
        $returns = (float) DB::table('credit_notes as cn')
            ->join('invoices as i', 'i.id', '=', 'cn.invoice_id')
            ->whereBetween('cn.credit_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(CASE WHEN i.total > 0 THEN cn.total * (i.subtotal - i.discount) / i.total ELSE 0 END), 0) as v')
            ->value('v');

        // Cost of goods sold for the period's invoices, less goods returned to stock.
        $cogs = (float) DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->where('i.status', '!=', 'cancelled')
            ->whereBetween('i.invoice_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(ii.unit_cost * ii.quantity), 0) as v')->value('v');
        $restockedCost = (float) DB::table('credit_note_items as ci')
            ->join('credit_notes as cn', 'cn.id', '=', 'ci.credit_note_id')
            ->join('invoice_items as ii', 'ii.id', '=', 'ci.invoice_item_id')
            ->where('cn.restock', true)
            ->whereBetween('cn.credit_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(ii.unit_cost * ci.quantity), 0) as v')->value('v');

        // Stock counted short / written off (negative adjustments) and found (positive), at current cost.
        $adjustments = (float) DB::table('stock_movements as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->where('m.type', 'adjustment')
            ->whereBetween(DB::raw('DATE(m.created_at)'), [$from, $to])
            ->selectRaw('COALESCE(SUM(m.quantity * p.cost_price), 0) as v')->value('v');

        $expenses = DB::table('expenses as e')
            ->join('expense_categories as c', 'c.id', '=', 'e.expense_category_id')
            ->whereBetween('e.expense_date', [$from, $to])
            ->groupBy('c.name')->orderBy('c.name')
            ->selectRaw('c.name, SUM(e.amount) as total')
            ->pluck('total', 'name')->map(fn ($v) => (float) $v);

        $netSales = $grossSales - $discounts - $returns;
        $costOfSales = $cogs - $restockedCost;
        $grossProfit = $netSales - $costOfSales;
        $netProfit = $grossProfit + $adjustments - $expenses->sum();

        $lines = [
            ['Sales (before discounts, excl. tax)', $grossSales, 'plain'],
            ['Less: invoice discounts', -$discounts, 'plain'],
            ['Less: returns / credit notes', -$returns, 'plain'],
            ['Net sales', $netSales, 'subtotal'],
            ['Cost of goods sold', -$costOfSales, 'plain'],
            ['Gross profit', $grossProfit, 'subtotal'],
            ['Stock adjustments (count differences, write-offs)', $adjustments, 'plain'],
        ];
        foreach ($expenses as $name => $amount) {
            $lines[] = ['Expense: '.$name, -$amount, 'expense'];
        }
        $lines[] = ['Total expenses', -$expenses->sum(), 'subtotal'];
        $lines[] = ['Net profit', $netProfit, 'total'];

        $period = Carbon::parse($from)->format('d/m/Y').' to '.Carbon::parse($to)->format('d/m/Y');

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'profit-and-loss', 'Profit & loss', ['Line' => 'text', 'Amount' => 'money'],
                collect($lines)->map(fn ($l) => [$l[0], $l[1]]), $period);
        }

        return view('reports.profit-loss', compact('lines', 'from', 'to', 'netSales', 'grossProfit', 'netProfit', 'taxCollected'));
    }

    /** Day-by-day: sales by channel, returns, money received by method, expenses. */
    public function daily(Request $request)
    {
        $from = $request->input('from', today()->subDays(13)->toDateString());
        $to = $request->input('to', today()->toDateString());

        $sales = DB::table('invoices')->where('status', '!=', 'cancelled')->whereBetween('invoice_date', [$from, $to])
            ->groupBy('invoice_date')->selectRaw('invoice_date as d, SUM(total) as total, COUNT(*) as n')->get();
        $returns = DB::table('credit_notes')->whereBetween('credit_date', [$from, $to])
            ->groupBy('credit_date')->selectRaw('credit_date as d, SUM(total) as total')->pluck('total', 'd');
        $received = \App\Models\Payment::query()->settled()->whereBetween('payment_date', [$from, $to])
            ->groupBy('payment_date', 'method')->selectRaw('payment_date as d, method, SUM(amount) as total')->get();
        $expenses = DB::table('expenses')->whereBetween('expense_date', [$from, $to])
            ->groupBy('expense_date')->selectRaw('expense_date as d, SUM(amount) as total')->pluck('total', 'd');

        $rows = collect();
        for ($day = Carbon::parse($to); $day->gte(Carbon::parse($from)); $day->subDay()) {
            $d = $day->toDateString();
            $daySales = $sales->where('d', $d);
            $dayReceived = $received->where('d', $d);
            $rows->push([
                'date' => $day->copy(),
                'invoices' => (int) $daySales->sum('n'),
                'sales' => (float) $daySales->sum('total'),
                'returns' => (float) ($returns[$d] ?? 0),
                'cash' => (float) $dayReceived->where('method', 'cash')->sum('total'),
                'cheque' => (float) $dayReceived->where('method', 'cheque')->sum('total'),
                'bank_transfer' => (float) $dayReceived->where('method', 'bank_transfer')->sum('total'),
                'card' => (float) $dayReceived->where('method', 'card')->sum('total'),
                'expenses' => (float) ($expenses[$d] ?? 0),
            ]);
        }

        $keys = ['invoices', 'sales', 'returns', 'cash', 'cheque', 'bank_transfer', 'card', 'expenses'];
        $totals = collect($keys)->mapWithKeys(fn ($k) => [$k => $rows->sum($k)])->all();

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'daily-summary', 'Daily summary', [
                'Date' => 'text', 'Invoices' => 'number', 'Sales' => 'money', 'Returns' => 'money',
                'Cash' => 'money', 'Cheque' => 'money', 'Bank transfer' => 'money', 'Card' => 'money', 'Expenses' => 'money',
            ], $rows->map(fn ($r) => array_merge([$r['date']->format('d/m/Y D')], array_map(fn ($k) => $r[$k], $keys))),
                Carbon::parse($from)->format('d/m/Y').' to '.Carbon::parse($to)->format('d/m/Y'),
                array_merge(['Total'], array_values($totals)));
        }

        return view('reports.daily', compact('rows', 'totals', 'from', 'to'));
    }
}