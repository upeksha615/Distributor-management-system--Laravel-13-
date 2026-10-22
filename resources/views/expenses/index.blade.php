<x-layouts.app title="Expenses">
    <x-page-header title="Expenses">
        <x-export-buttons />
        <a href="{{ route('reports.expenses') }}" class="btn btn-secondary">Expense report</a>
        <a href="{{ route('expense-categories.index') }}" class="btn btn-secondary">Categories</a>
        <a href="{{ route('expenses.create') }}" class="btn btn-primary">Record expense</a>
    </x-page-header>

    <x-search-bar placeholder="Payee, description, reference or number">
        <x-select label="Category" name="category_id" :options="$categories" :value="request('category_id')" placeholder="All" class="w-48" />
        <x-select label="Method" name="method" :options="\App\Models\Expense::METHODS" :value="request('method')" placeholder="All" class="w-36" />
        <x-field label="From" name="from" type="date" :value="request('from')" class="w-40" />
        <x-field label="To" name="to" type="date" :value="request('to')" class="w-40" />
    </x-search-bar>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Number</th><th>Date</th><th>Category</th><th>Payee</th><th>Description</th><th>Method</th><th>Reference</th><th class="num">Amount</th><th class="w-40"></th></tr></thead>
            <tbody>
            @forelse ($expenses as $e)
                <tr>
                    <td class="whitespace-nowrap">{{ $e->number }}</td>
                    <td>{{ $e->expense_date->format('d/m/Y') }}</td>
                    <td>{{ $e->category?->name }}</td>
                    <td>{{ $e->payee }}</td>
                    <td>{{ $e->description }}</td>
                    <td>{{ \App\Models\Expense::METHODS[$e->method] ?? $e->method }}</td>
                    <td>{{ $e->reference }}</td>
                    <td class="num">{{ money($e->amount, false) }}</td>
                    <td class="text-right whitespace-nowrap">
                            <a href="{{ route('expenses.edit', $e) }}" class="btn btn-secondary btn-sm">Edit</a>
                            <form method="POST" action="{{ route('expenses.destroy', $e) }}" class="inline" onsubmit="return confirm('Delete expense {{ $e->number }} ({{ money($e->amount) }})? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm">Delete</button>
                            </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-6 text-center text-slate-500">No expenses found.</td></tr>
            @endforelse
            </tbody>
            @if ($expenses->total() > 0)
                <tfoot>
                <tr class="bg-slate-50 font-semibold">
                    <td colspan="7" class="px-3 py-2">Total ({{ $expenses->total() }} {{ \Illuminate\Support\Str::plural('expense', $expenses->total()) }})</td>
                    <td class="num px-3 py-2">{{ money($total, false) }}</td>
                    <td></td>
                </tr>
                </tfoot>
            @endif
        </table>
    </div>
    <div class="mt-4">{{ $expenses->links() }}</div>
</x-layouts.app>
