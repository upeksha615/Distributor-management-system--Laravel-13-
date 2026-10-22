<x-layouts.app title="Expense report">
    <x-page-header title="Expense report" subtitle="Totals by category, including POS cash-outs">
        <x-export-buttons />
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3 no-print">
        <x-field label="From" name="from" type="date" :value="$from" class="w-40" />
        <x-field label="To" name="to" type="date" :value="$to" class="w-40" />
        <button class="btn btn-primary">Run report</button>
    </form>

    <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Total expenses" :value="money($total)" tone="red" />
        <x-stat label="Entries" :value="$rows->sum('entries')" />
        <x-stat label="Categories used" :value="$rows->count()" />
        <x-stat label="Largest category" :value="$rows->first()->name ?? '-'" />
    </div>

    <div class="card overflow-x-auto">
        <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">By category · {{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') }} to {{ \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') }}</h2>
        <table class="table">
            <thead><tr><th>Category</th><th class="num">Entries</th><th class="num">Amount</th><th class="num">% of total</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td><a href="{{ route('expenses.index', ['category_id' => $row->id, 'from' => $from, 'to' => $to]) }}" class="link">{{ $row->name }}</a></td>
                    <td class="num">{{ $row->entries }}</td>
                    <td class="num">{{ money($row->amount, false) }}</td>
                    <td class="num">{{ $total > 0 ? number_format($row->amount / $total * 100, 1) : 0 }}%</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-6 text-center text-slate-500">No expenses in this period.</td></tr>
            @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                <tr class="bg-slate-50 font-semibold">
                    <td class="px-3 py-2">Total</td>
                    <td class="num px-3 py-2">{{ $rows->sum('entries') }}</td>
                    <td class="num px-3 py-2">{{ money($total, false) }}</td>
                    <td class="num px-3 py-2">100%</td>
                </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="card mt-6 overflow-x-auto">
        <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Last 6 months by category</h2>
        <table class="table">
            <thead><tr><th>Category</th>@foreach ($months as $label)<th class="num">{{ $label }}</th>@endforeach<th class="num">Total</th></tr></thead>
            <tbody>
            @forelse ($matrix as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    @foreach ($row['months'] as $amount)<td class="num">{{ $amount ? money($amount, false) : '-' }}</td>@endforeach
                    <td class="num font-medium">{{ money($row['total'], false) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $months->count() + 2 }}" class="py-6 text-center text-slate-500">No expenses in the last 6 months.</td></tr>
            @endforelse
            </tbody>
            @if ($matrix->isNotEmpty())
                <tfoot>
                <tr class="bg-slate-50 font-semibold">
                    <td class="px-3 py-2">Total</td>
                    @foreach ($monthTotals as $amount)<td class="num px-3 py-2">{{ money($amount, false) }}</td>@endforeach
                    <td class="num px-3 py-2">{{ money($monthTotals->sum(), false) }}</td>
                </tr>
                </tfoot>
            @endif
        </table>
    </div>
</x-layouts.app>
