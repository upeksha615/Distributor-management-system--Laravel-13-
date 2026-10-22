<x-layouts.app :title="'Statement - '.$supplier->name">
    <x-page-header :title="'Supplier statement: '.$supplier->name">
        <form method="GET" class="flex items-end gap-2">
            <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="input w-40">
            <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="input w-40">
            <button class="btn btn-secondary">Update</button>
        </form>
        <x-export-buttons />
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
        <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <div class="card card-body">
        @include('partials.document-header', ['docTitle' => 'SUPPLIER STATEMENT', 'docNumber' => $from->format('d/m/Y').' - '.$to->format('d/m/Y')])

        <div class="mb-6 text-sm">
            <div class="font-medium">{{ $supplier->name }} ({{ $supplier->code }})</div>
            <div>{{ collect([$supplier->address, $supplier->country])->filter()->join(', ') }}</div>
            <div>{{ collect([$supplier->phone, $supplier->email])->filter()->join(' · ') }}</div>
        </div>

        <table class="table">
            <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Details</th><th class="num">Debit (paid)</th><th class="num">Credit (billed)</th><th class="num">Balance</th></tr></thead>
            <tbody>
            <tr class="font-medium"><td>{{ $from->format('d/m/Y') }}</td><td colspan="5">Opening balance</td><td class="num">{{ money($opening, false) }}</td></tr>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['date']->format('d/m/Y') }}</td>
                    <td>{{ $line['type'] }}</td>
                    <td><a href="{{ $line['url'] }}" class="link">{{ $line['number'] }}</a></td>
                    <td class="text-slate-600">{{ $line['description'] }}</td>
                    <td class="num">{{ $line['debit'] ? money($line['debit'], false) : '' }}</td>
                    <td class="num">{{ $line['credit'] ? money($line['credit'], false) : '' }}</td>
                    <td class="num">{{ money($line['balance'], false) }}</td>
                </tr>
            @endforeach
            <tr class="font-semibold"><td colspan="6" class="text-right">Closing balance (we owe)</td><td class="num">{{ money($running) }}</td></tr>
            </tbody>
        </table>
    </div>
</x-layouts.app>
