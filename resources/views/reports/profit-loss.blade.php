<x-layouts.app title="Profit & loss">
    <x-page-header title="Profit & loss" subtitle="Excludes tax. Cost of sales uses the cost price at the time of each sale.">
        <x-export-buttons />
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3 no-print">
        <x-field label="From" name="from" type="date" :value="$from" class="w-40" />
        <x-field label="To" name="to" type="date" :value="$to" class="w-40" />
        <button class="btn btn-primary">Run</button>
        <a href="{{ route('reports.profit-loss', ['from' => today()->startOfMonth()->toDateString(), 'to' => today()->toDateString()]) }}" class="btn btn-secondary">This month</a>
        <a href="{{ route('reports.profit-loss', ['from' => today()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'to' => today()->subMonthNoOverflow()->endOfMonth()->toDateString()]) }}" class="btn btn-secondary">Last month</a>
        <a href="{{ route('reports.profit-loss', ['from' => today()->startOfYear()->toDateString(), 'to' => today()->toDateString()]) }}" class="btn btn-secondary">Year to date</a>
    </form>

    <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Net sales" :value="money($netSales)" />
        <x-stat label="Gross profit" :value="money($grossProfit)" tone="green" />
        <x-stat label="Gross margin" :value="$netSales > 0 ? number_format($grossProfit / $netSales * 100, 1).'%' : '-'" />
        <x-stat label="Net profit" :value="money($netProfit)" :tone="$netProfit < 0 ? 'red' : 'green'" />
    </div>

    <div class="card max-w-3xl">
        <table class="table">
            <tbody>
            @foreach ($lines as [$label, $amount, $style])
                <tr class="{{ in_array($style, ['subtotal', 'total']) ? 'font-semibold bg-slate-50' : '' }} {{ $style === 'total' ? 'text-base' : '' }}">
                    <td class="{{ $style === 'expense' ? 'pl-8 text-slate-600' : '' }}">{{ $label }}</td>
                    <td class="num {{ $amount < 0 ? 'text-red-700' : '' }}">{{ money($amount, false) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-sm text-slate-500">Tax collected on sales in this period (not income): {{ money($taxCollected) }}. Supplier freight, duty and other bills are counted when entered as expenses.</p>
</x-layouts.app>
