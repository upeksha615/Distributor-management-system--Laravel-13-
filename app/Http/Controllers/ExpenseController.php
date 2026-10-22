<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\DocumentNumber;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public const DEFAULT_CATEGORIES = [
        'Rent', 'Electricity', 'Water', 'Telephone & internet', 'Salaries & wages', 'Transport & fuel',
        'Courier & freight', 'Customs & clearing', 'Repairs & maintenance', 'Stationery',
        'Tea & refreshments', 'Bank charges', 'Advertising', 'Other',
    ];

    public function index(Request $request)
    {
        $expenses = Expense::with(['category', 'user'])
            ->when($request->category_id, fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($request->method, fn ($q, $m) => $q->where('method', $m))
            ->when($request->from, fn ($q, $d) => $q->where('expense_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('expense_date', '<=', $d))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w->where('payee', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")
                ->orWhere('number', 'like', "%{$term}%")))
            ->orderByDesc('expense_date')->orderByDesc('id');

        if ($format = $this->exportFormat($request)) {
            $rows = $expenses->get();

            return Exporter::download($format, 'expenses', 'Expenses', [
                'Number' => 'text', 'Date' => 'text', 'Category' => 'text', 'Payee' => 'text', 'Description' => 'text',
                'Method' => 'text', 'Reference' => 'text', 'Amount' => 'money',
            ], $rows->map(fn ($e) => [
                $e->number, $e->expense_date->format('d/m/Y'), $e->category?->name, $e->payee, $e->description,
                Expense::METHODS[$e->method] ?? $e->method, $e->reference, $e->amount,
            ]), $this->filterSummary($request), ['Total', null, null, null, null, null, null, $rows->sum('amount')]);
        }

        $total = (float) (clone $expenses)->reorder()->sum('amount');
        $expenses = $expenses->paginate(25)->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'total' => $total,
            'categories' => ExpenseCategory::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create()
    {
        $this->seedDefaultCategories();

        return view('expenses.form', [
            'expense' => new Expense(['expense_date' => today(), 'method' => 'cash']),
            'categories' => ExpenseCategory::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $expense = DB::transaction(fn () => Expense::create($data + [
            'number' => DocumentNumber::next('expenses', 'EXP'),
            'user_id' => auth()->id(),
        ]));

        return redirect()->route('expenses.index')->with('success', "Expense {$expense->number} recorded.");
    }

    public function edit(Expense $expense)
    {
        $this->ensureEditable($expense);

        return view('expenses.form', [
            'expense' => $expense,
            'categories' => ExpenseCategory::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $this->ensureEditable($expense);
        $expense->update($this->validated($request));

        return redirect()->route('expenses.index')->with('success', "Expense {$expense->number} updated.");
    }

    public function destroy(Expense $expense)
    {
        $this->ensureEditable($expense);

        DB::transaction(function () use ($expense) {
            AuditLog::record('expense.delete', $expense, "Deleted expense {$expense->number} ({$expense->category?->name}, "
                .money($expense->amount).', '.$expense->expense_date->format('d/m/Y').($expense->payee ? ', '.$expense->payee : '').')');
            $expense->delete();
        });

        return redirect()->route('expenses.index')->with('success', "Expense {$expense->number} deleted.");
    }

    /* ---------- Categories ---------- */

    public function categories()
    {
        $this->seedDefaultCategories();
        $rows = ExpenseCategory::withCount('expenses')->orderBy('name')->get();

        return view('expenses.categories', compact('rows'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'max:255', Rule::unique('expense_categories', 'name')]]);
        ExpenseCategory::create($data);

        return back()->with('success', 'Expense category added.');
    }

    public function updateCategory(Request $request, ExpenseCategory $category)
    {
        $data = $request->validate(['name' => ['required', 'max:255', Rule::unique('expense_categories', 'name')->ignore($category->id)]]);
        $category->update($data);

        return back()->with('success', 'Expense category updated.');
    }

    public function destroyCategory(ExpenseCategory $category)
    {
        $count = $category->expenses()->count();

        if ($count > 0) {
            return back()->with('error', "This category is used by {$count} expense(s) and cannot be deleted.");
        }

        $category->delete();

        return back()->with('success', 'Expense category deleted.');
    }

    /* ---------- Report ---------- */

    /** Expense totals by category for a date range, plus a 6-month breakdown by category. */
    public function report(Request $request)
    {
        $from = $request->input('from', today()->startOfMonth()->toDateString());
        $to = $request->input('to', today()->toDateString());

        $rows = DB::table('expenses as e')
            ->join('expense_categories as c', 'c.id', '=', 'e.expense_category_id')
            ->whereBetween('e.expense_date', [$from, $to])
            ->groupBy('c.id', 'c.name')
            ->select('c.id', 'c.name')
            ->selectRaw('COUNT(*) as entries')
            ->selectRaw('SUM(e.amount) as amount')
            ->orderByDesc('amount')
            ->get();
        $total = (float) $rows->sum('amount');

        // Last 6 months, ending with the month of the "to" date.
        $end = Carbon::parse($to)->startOfMonth();
        $months = collect(range(5, 0))->mapWithKeys(fn ($i) => [$end->copy()->subMonths($i)->format('Y-m') => $end->copy()->subMonths($i)->format('M Y')]);

        $monthly = DB::table('expenses as e')
            ->join('expense_categories as c', 'c.id', '=', 'e.expense_category_id')
            ->whereBetween('e.expense_date', [$end->copy()->subMonths(5)->toDateString(), $end->copy()->endOfMonth()->toDateString()])
            ->groupBy('c.id', 'c.name', DB::raw("DATE_FORMAT(e.expense_date, '%Y-%m')"))
            ->select('c.id', 'c.name')
            ->selectRaw("DATE_FORMAT(e.expense_date, '%Y-%m') as ym")
            ->selectRaw('SUM(e.amount) as amount')
            ->get();

        $matrix = $monthly->groupBy('id')->map(fn ($g) => [
            'name' => $g->first()->name,
            'months' => $months->keys()->mapWithKeys(fn ($ym) => [$ym => (float) $g->where('ym', $ym)->sum('amount')])->all(),
        ])->map(fn ($r) => $r + ['total' => array_sum($r['months'])])->sortByDesc('total');
        $monthTotals = $months->keys()->mapWithKeys(fn ($ym) => [$ym => (float) $monthly->where('ym', $ym)->sum('amount')]);

        if ($format = $this->exportFormat($request)) {
            // One row per category that has spending in the period or in any of the 6 months.
            $names = $rows->pluck('name', 'id')->union($matrix->map(fn ($r) => $r['name']))->sort();
            $byId = $rows->keyBy('id');
            $columns = ['Category' => 'text', 'Entries' => 'number', 'Amount (period)' => 'money', '% of period' => 'qty']
                + $months->mapWithKeys(fn ($label) => [$label => 'money'])->all();

            return Exporter::download($format, 'expense-report', 'Expenses by category', $columns,
                $names->map(fn ($name, $id) => array_merge([
                    $name, $byId[$id]->entries ?? 0, (float) ($byId[$id]->amount ?? 0),
                    $total > 0 ? round(($byId[$id]->amount ?? 0) / $total * 100, 1) : 0,
                ], array_values($matrix[$id]['months'] ?? array_fill_keys($months->keys()->all(), 0))))->values(),
                Carbon::parse($from)->format('d/m/Y').' to '.Carbon::parse($to)->format('d/m/Y').' · monthly columns: last 6 months',
                array_merge(['Total', $rows->sum('entries'), $total, $total > 0 ? 100 : 0], $monthTotals->values()->all()));
        }

        return view('expenses.report', compact('rows', 'total', 'from', 'to', 'months', 'matrix', 'monthTotals'));
    }

    /* ---------- Helpers ---------- */

    private function validated(Request $request): array
    {
        return $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(array_keys(Expense::METHODS))],
            'payee' => ['nullable', 'max:255'],
            'reference' => ['nullable', 'max:100'],
            'description' => ['nullable', 'max:255'],
        ]);
    }

    private function ensureEditable(Expense $expense): void
    {
        // All expenses are entered here, so all can be edited (changes are audited on delete).
    }

    private function seedDefaultCategories(): void
    {
        if (ExpenseCategory::exists()) {
            return;
        }

        foreach (self::DEFAULT_CATEGORIES as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name]);
        }
    }
}
