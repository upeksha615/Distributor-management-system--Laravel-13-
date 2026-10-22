<x-layouts.app title="Sales & profit">
    <x-page-header title="Sales & gross profit" subtitle="Net of returns, before invoice-level discount and tax">
        <x-export-buttons />
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3 no-print">
        <x-field label="From" name="from" type="date" :value="$from" class="w-40" />
        <x-field label="To" name="to" type="date" :value="$to" class="w-40" />
        <x-select label="Group by" name="group" :value="$group" class="w-40"
                  :options="['customer' => 'Customer', 'product' => 'Product', 'brand' => 'Brand', 'rep' => 'Sales rep', 'day' => 'Day']" />
        <button class="btn btn-primary">Run report</button>
    </form>

    @php
        $totalSales = $rows->sum('sales');
        $totalCost = $rows->sum('cost');
    @endphp
    <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Sales" :value="money($totalSales)" />
        <x-stat label="Cost of sales" :value="money($totalCost)" />
        <x-stat label="Gross profit" :value="money($totalSales - $totalCost)" tone="green" />
        <x-stat label="Margin" :value="$totalSales > 0 ? number_format(($totalSales - $totalCost) / $totalSales * 100, 1).'%' : '-'" />
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ ucfirst($group === 'rep' ? 'sales rep' : $group) }}</th><th class="num">Invoices</th><th class="num">Qty</th><th class="num">Sales</th><th class="num">Cost</th><th class="num">Gross profit</th><th class="num">Margin</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $group === 'day' ? \Illuminate\Support\Carbon::parse($row->label)->format('d/m/Y D') : $row->label }}</td>
                    <td class="num">{{ $row->invoices }}</td>
                    <td class="num">{{ qty($row->qty) }}</td>
                    <td class="num">{{ money($row->sales, false) }}</td>
                    <td class="num">{{ money($row->cost, false) }}</td>
                    <td class="num">{{ money($row->sales - $row->cost, false) }}</td>
                    <td class="num">{{ $row->sales > 0 ? number_format(($row->sales - $row->cost) / $row->sales * 100, 1).'%' : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">No sales in this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
