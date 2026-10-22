<x-layouts.app title="Aged creditors">
    <x-page-header title="Aged creditors" subtitle="Open supplier bill balances by days past due date, as of {{ today()->format('d/m/Y') }}">
        <form method="GET" class="flex items-end gap-2">
            <select name="supplier_id" class="input w-48" onchange="this.form.submit()">
                <option value="">All suppliers</option>
                @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </form>
        <x-export-buttons />
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
            <tr>
                <th>Supplier</th><th class="num">Terms</th>
                @foreach ($buckets as $label)<th class="num">{{ $label }}</th>@endforeach
                <th class="num">Total bills</th><th class="num">Unallocated paid</th><th class="num">Net owing</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>
                        <a href="{{ route('suppliers.show', $row['supplier']) }}" class="link">{{ $row['supplier']->name }}</a>
                        <a href="{{ route('suppliers.statement', $row['supplier']) }}" class="ml-1 text-xs text-slate-500 no-print">statement</a>
                    </td>
                    <td class="num text-slate-500">{{ $row['supplier']->payment_terms_days ?? 30 }}d</td>
                    @foreach ($buckets as $key => $label)
                        <td class="num {{ in_array($key, ['d60', 'd90', 'd90plus']) && $row[$key] > 0 ? 'text-red-600' : '' }}">{{ $row[$key] ? money($row[$key], false) : '-' }}</td>
                    @endforeach
                    <td class="num">{{ money($row['total'], false) }}</td>
                    <td class="num">{{ $row['advance'] ? money(-$row['advance'], false) : '-' }}</td>
                    <td class="num font-semibold">{{ money($row['net'], false) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ count($buckets) + 5 }}" class="py-6 text-center text-slate-500">Nothing owed to suppliers.</td></tr>
            @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                <tr class="bg-slate-50 font-semibold">
                    <td colspan="2" class="px-3 py-2">Total</td>
                    @foreach ($buckets as $key => $label)<td class="num px-3 py-2">{{ money($totals[$key], false) }}</td>@endforeach
                    <td class="num px-3 py-2">{{ money($totals['total'], false) }}</td>
                    <td class="num px-3 py-2">{{ $totals['advance'] ? money(-$totals['advance'], false) : '-' }}</td>
                    <td class="num px-3 py-2">{{ money($totals['net'], false) }}</td>
                </tr>
                <tr class="text-xs text-slate-500">
                    <td colspan="2" class="px-3 py-1">% of total</td>
                    @foreach ($buckets as $key => $label)<td class="num px-3 py-1">{{ $totals['total'] > 0 ? number_format($totals[$key] / $totals['total'] * 100, 1) : 0 }}%</td>@endforeach
                    <td colspan="3"></td>
                </tr>
                </tfoot>
            @endif
        </table>
    </div>
    <p class="mt-3 text-xs text-slate-500">Unallocated payments are advances not yet applied to a bill. Apply them from the supplier payment page.</p>
</x-layouts.app>
