<x-layouts.app :title="$payment->number">
    <x-page-header :title="'Supplier payment '.$payment->number">
        <a href="{{ route('suppliers.show', $payment->supplier) }}" class="btn btn-secondary">View supplier</a>
        <a href="{{ route('supplier-payments.pdf', $payment) }}" class="btn btn-secondary">Download voucher PDF</a>
        <button onclick="window.print()" class="btn btn-secondary">Print voucher</button>
    </x-page-header>

    <div class="card card-body">
        @include('partials.document-header', ['docTitle' => 'PAYMENT VOUCHER', 'docNumber' => $payment->number])

        <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
            <dt class="text-slate-500">Paid to</dt><dd class="font-medium">{{ $payment->supplier->name }} ({{ $payment->supplier->code }})</dd>
            <dt class="text-slate-500">Date</dt><dd>{{ $payment->payment_date->format('d/m/Y') }}</dd>
            <dt class="text-slate-500">Method</dt><dd>{{ $payment->methodLabel() }}</dd>
            <dt class="text-slate-500">Amount</dt><dd class="text-lg font-semibold">{{ money($payment->amount) }}</dd>
            @if ($payment->method === 'cheque')
                <dt class="text-slate-500">Cheque no</dt><dd>{{ $payment->cheque_number }}</dd>
                <dt class="text-slate-500">Cheque date</dt><dd>{{ $payment->cheque_date?->format('d/m/Y') }}</dd>
            @endif
            @if ($payment->reference)<dt class="text-slate-500">Reference</dt><dd>{{ $payment->reference }}</dd>@endif
            <dt class="text-slate-500">Prepared by</dt><dd>{{ $payment->user?->name }}</dd>
        </dl>
        @if ($payment->notes)<p class="mt-3 whitespace-pre-line text-sm"><span class="text-slate-500">Notes:</span> {{ $payment->notes }}</p>@endif

        <h3 class="mt-6 mb-2 font-semibold">Applied to bills</h3>
        <table class="table">
            <thead><tr><th>Bill</th><th>Supplier inv</th><th>Description</th><th>Bill date</th><th class="num">Amount applied</th></tr></thead>
            <tbody>
            @forelse ($payment->allocations as $a)
                <tr>
                    <td><a href="{{ route('supplier-bills.show', $a->bill) }}" class="link">{{ $a->bill->number }}</a></td>
                    <td>{{ $a->bill->supplier_invoice_no ?: '-' }}</td>
                    <td class="text-slate-600">{{ $a->bill->description }}</td>
                    <td>{{ $a->bill->bill_date->format('d/m/Y') }}</td>
                    <td class="num">{{ money($a->amount, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-center text-slate-500">Not applied to any bill.</td></tr>
            @endforelse
            <tr class="font-medium"><td colspan="4" class="text-right">Unallocated (advance on account)</td><td class="num">{{ money($payment->unallocatedAmount(), false) }}</td></tr>
            </tbody>
        </table>

        <div class="mt-16 hidden grid-cols-2 gap-16 text-center text-sm print:grid">
            <div class="border-t border-slate-400 pt-1">Approved by</div>
            <div class="border-t border-slate-400 pt-1">Received by</div>
        </div>
    </div>

    @if ($openBills->isNotEmpty())
        <form method="POST" action="{{ route('supplier-payments.allocate', $payment) }}" class="card mt-6 no-print">
            @csrf
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Apply unallocated {{ money($payment->unallocatedAmount()) }} to bills</h2>
            @error('allocations')<p class="error px-5 pt-2">{{ $message }}</p>@enderror
            <table class="table">
                <thead><tr><th>Bill</th><th>Description</th><th>Due</th><th class="num">Balance</th><th class="w-40 num">Apply</th></tr></thead>
                <tbody>
                @foreach ($openBills as $bill)
                    <tr>
                        <td>{{ $bill->number }}</td>
                        <td class="text-slate-600">{{ $bill->description }}</td>
                        <td class="{{ $bill->daysOverdue() ? 'text-red-600' : '' }}">{{ $bill->due_date->format('d/m/Y') }}</td>
                        <td class="num">{{ money($bill->balance(), false) }}</td>
                        <td><input type="number" step="0.01" min="0" max="{{ $bill->balance() }}" name="allocations[{{ $bill->id }}]" class="input text-right"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="px-5 py-3"><button class="btn btn-primary">Apply</button></div>
        </form>
    @endif
</x-layouts.app>
