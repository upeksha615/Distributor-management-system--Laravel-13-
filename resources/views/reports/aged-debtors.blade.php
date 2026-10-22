<x-layouts.app title="Aged debtors">
    <x-page-header title="Aged debtors" subtitle="Open balances by days past due date, as of {{ today()->format('d/m/Y') }}">
        <form method="GET" class="flex items-end gap-2">
            <select name="sales_rep_id" class="input w-48" onchange="this.form.submit()">
                <option value="">All sales reps</option>
                @foreach ($reps as $rep)<option value="{{ $rep->id }}" @selected(request('sales_rep_id') == $rep->id)>{{ $rep->name }}</option>@endforeach
            </select>
        </form>
        <x-export-buttons />
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
            <tr>
                <th>Customer</th><th>Rep</th><th class="num">Credit limit</th>
                @foreach ($buckets as $label)<th class="num">{{ $label }}</th>@endforeach
                <th class="num">Total</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>
                        <a href="{{ route('customers.show', $row['customer']) }}" class="link">{{ $row['customer']->name }}</a>
                        @if ($row['customer']->on_hold)<x-status status="on hold" class="ml-1" />@endif
                    </td>
                    <td class="text-slate-500">{{ $row['customer']->salesRep?->name }}</td>
                    <td class="num">{{ $row['customer']->credit_limit > 0 ? money($row['customer']->credit_limit, false) : '-' }}</td>
                    @foreach ($buckets as $key => $label)
                        <td class="num {{ in_array($key, ['d60', 'd90', 'd90plus']) && $row[$key] > 0 ? 'text-red-600' : '' }}">{{ $row[$key] ? money($row[$key], false) : '-' }}</td>
                    @endforeach
                    <td class="num font-semibold">{{ money($row['total'], false) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ count($buckets) + 4 }}" class="py-6 text-center text-slate-500">No outstanding balances.</td></tr>
            @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                <tr class="bg-slate-50 font-semibold">
                    <td colspan="3" class="px-3 py-2">Total</td>
                    @foreach ($buckets as $key => $label)<td class="num px-3 py-2">{{ money($totals[$key], false) }}</td>@endforeach
                    <td class="num px-3 py-2">{{ money($totals['total'], false) }}</td>
                </tr>
                <tr class="text-xs text-slate-500">
                    <td colspan="3" class="px-3 py-1">% of total</td>
                    @foreach ($buckets as $key => $label)<td class="num px-3 py-1">{{ $totals['total'] > 0 ? number_format($totals[$key] / $totals['total'] * 100, 1) : 0 }}%</td>@endforeach
                    <td></td>
                </tr>
                </tfoot>
            @endif
        </table>
    </div>
    <p class="mt-3 text-xs text-slate-500">Unallocated payments (customer advances) are not deducted here. Apply them to invoices from the payment page.</p>
</x-layouts.app>
