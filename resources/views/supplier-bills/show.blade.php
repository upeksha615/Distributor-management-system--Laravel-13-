<x-layouts.app :title="$bill->number">
    <x-page-header :title="'Supplier bill '.$bill->number">
        <a href="{{ route('suppliers.show', $bill->supplier) }}" class="btn btn-secondary">View supplier</a>
        @if (in_array($bill->status, ['unpaid', 'partial']))
            <a href="{{ route('supplier-payments.create', ['supplier_id' => $bill->supplier_id]) }}" class="btn btn-primary">Make payment</a>
        @endif
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    @if ($bill->status === 'cancelled')
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">This bill is <strong>cancelled</strong> and is not owed.</div>
    @endif

    @php $open = in_array($bill->status, ['unpaid', 'partial']); @endphp

    <div class="card card-body">
        @include('partials.document-header', ['docTitle' => 'SUPPLIER BILL', 'docNumber' => $bill->number])

        <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
            <dt class="text-slate-500">Supplier</dt><dd class="font-medium">{{ $bill->supplier->name }} ({{ $bill->supplier->code }})</dd>
            <dt class="text-slate-500">Supplier invoice</dt><dd>{{ $bill->supplier_invoice_no ?: '-' }}</dd>
            <dt class="text-slate-500">Bill date</dt><dd>{{ $bill->bill_date->format('d/m/Y') }}</dd>
            <dt class="text-slate-500">Due date</dt>
            <dd class="{{ $open && $bill->daysOverdue() ? 'text-red-600' : '' }}">
                {{ $bill->due_date->format('d/m/Y') }}@if ($open && $bill->daysOverdue()) ({{ $bill->daysOverdue() }} days overdue)@endif
            </dd>
            @if ($bill->goodsReceipt)
                <dt class="text-slate-500">Goods received</dt>
                <dd><a href="{{ route('goods-receipts.show', $bill->goodsReceipt) }}" class="link">{{ $bill->goodsReceipt->number }}</a></dd>
                <dt class="text-slate-500">Purchase order</dt>
                <dd><a href="{{ route('purchase-orders.show', $bill->goodsReceipt->purchase_order_id) }}" class="link">{{ $bill->goodsReceipt->purchaseOrder?->number }}</a></dd>
            @endif
            <dt class="text-slate-500">Status</dt><dd><x-status :status="$bill->status" /></dd>
            <dt class="text-slate-500">Amount</dt><dd class="text-lg font-semibold">{{ money($bill->amount) }}</dd>
            <dt class="text-slate-500">Paid</dt><dd>{{ money($bill->paid_amount) }}</dd>
            <dt class="text-slate-500">Balance</dt><dd class="font-semibold">{{ $bill->status === 'cancelled' ? '-' : money($bill->balance()) }}</dd>
        </dl>
        @if ($bill->description)<p class="mt-3 text-sm"><span class="text-slate-500">Description:</span> {{ $bill->description }}</p>@endif

        <h3 class="mt-6 mb-2 font-semibold">Payments applied</h3>
        <table class="table">
            <thead><tr><th>Voucher</th><th>Date</th><th>Method</th><th class="num">Amount applied</th></tr></thead>
            <tbody>
            @forelse ($bill->allocations as $a)
                <tr>
                    <td><a href="{{ route('supplier-payments.show', $a->payment) }}" class="link">{{ $a->payment->number }}</a></td>
                    <td>{{ $a->payment->payment_date->format('d/m/Y') }}</td>
                    <td>{{ $a->payment->methodLabel() }}</td>
                    <td class="num">{{ money($a->amount, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-4 text-center text-slate-500">No payments yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($open && $bill->allocations->isEmpty())
        <form method="POST" action="{{ route('supplier-bills.cancel', $bill) }}" class="card card-body mt-6 flex flex-wrap items-end gap-3 no-print"
              onsubmit="return confirm('Cancel this bill? It will no longer be owed to the supplier.')">
            @csrf
            <div class="w-full font-semibold">Cancel bill</div>
            <x-field label="Reason" name="reason" placeholder="e.g. Entered twice" class="min-w-60 flex-1" />
            <button class="btn btn-danger">Cancel bill</button>
        </form>
    @endif
</x-layouts.app>
