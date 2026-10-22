<x-layouts.app title="Daily summary">
    <x-page-header title="Daily summary" subtitle="Sales, returns, money received and expenses per day">
        <x-export-buttons />
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3 no-print">
        <x-field label="From" name="from" type="date" :value="$from" class="w-40" />
        <x-field label="To" name="to" type="date" :value="$to" class="w-40" />
        <button class="btn btn-primary">Run</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
            <tr>
                <th>Date</th><th class="num">Invoices</th><th class="num">Sales</th><th class="num">Returns</th>
                <th class="num">Cash</th><th class="num">Cheque</th><th class="num">Bank</th><th class="num">Card</th><th class="num">Expenses</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($rows as $r)
                <tr class="{{ $r['date']->isSunday() ? 'bg-slate-50 text-slate-500' : '' }}">
                    <td class="whitespace-nowrap">{{ $r['date']->format('d/m/Y D') }}</td>
                    <td class="num">{{ $r['invoices'] ?: '' }}</td>
                    @foreach (['sales', 'returns', 'cash', 'cheque', 'bank_transfer', 'card', 'expenses'] as $k)
                        <td class="num">{{ $r[$k] ? money($r[$k], false) : '' }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr class="bg-slate-50 font-semibold">
                <td class="px-3 py-2">Total</td>
                <td class="num px-3 py-2">{{ $totals['invoices'] }}</td>
                @foreach (['sales', 'returns', 'cash', 'cheque', 'bank_transfer', 'card', 'expenses'] as $k)
                    <td class="num px-3 py-2">{{ money($totals[$k], false) }}</td>
                @endforeach
            </tr>
            </tfoot>
        </table>
    </div>
</x-layouts.app>
