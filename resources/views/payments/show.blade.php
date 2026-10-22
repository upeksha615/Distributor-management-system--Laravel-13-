<x-layouts.app :title="$payment->number">
    @php
        $user = auth()->user();
        $settled = $payment->isSettled();
    @endphp
    <x-page-header :title="'Payment '.$payment->number" :subtitle="$payment->customer->name.' · '.money($payment->amount).' · '.$payment->methodLabel()">
        <x-status :status="$payment->displayStatus()" class="self-center" />
        @if ($payment->method === 'cheque')<x-status :status="$payment->chequeStatus()" class="self-center" />@endif
        @if ($payment->status === 'verified')
            <a href="{{ route('payments.pdf', $payment) }}" class="btn btn-secondary">{{ $settled ? 'Receipt PDF' : 'Acknowledgement PDF' }}</a>
        @endif
        <a href="{{ route('customers.show', $payment->customer) }}" class="btn btn-secondary">Dealer</a>
    </x-page-header>

    {{-- Warnings --}}
    @if ($payment->duplicate_check === 'flagged')
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-800">
            <div class="font-semibold">Possible duplicate payment</div>
            <p>Matches {{ $duplicates->count() }} other record(s) by reference, cheque number, or dealer + amount + date. Check before verifying.</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($duplicates as $d)
                    <li><a href="{{ route('payments.show', $d) }}" class="link">{{ $d->number }}</a> · {{ $d->customer->name }} · {{ $d->payment_date->format('d/m/Y') }} · {{ money($d->amount) }} · {{ $d->reference ?: $d->cheque_number }} · {{ $d->statusLabel() }}</li>
                @endforeach
            </ul>
            @if ($payment->isOpen())
                <div class="mt-3 flex flex-wrap gap-2">
                    @can('payments.verify')
                        <form method="POST" action="{{ route('payments.action', [$payment, 'not-duplicate']) }}" class="flex gap-2">@csrf
                            <input name="note" required placeholder="Why it is not a duplicate" class="input w-64"><button class="btn btn-secondary btn-sm">Not a duplicate</button></form>
                    @endcan
                    @can('payments.record')
                        <form method="POST" action="{{ route('payments.action', [$payment, 'reject-duplicate']) }}" class="flex gap-2" onsubmit="return confirm('Reject this payment as a duplicate?')">@csrf
                            <input name="note" required placeholder="Note" class="input w-48"><button class="btn btn-danger btn-sm">Reject as duplicate</button></form>
                    @endcan
                </div>
            @endif
        </div>
    @endif
    @if ($payment->status === 'rejected')
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Rejected: {{ $payment->rejection_reason }}</div>
    @elseif ($payment->status === 'reversed')
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Reversed {{ $payment->reversed_at?->format('d/m/Y H:i') }} by {{ $payment->reverser?->name ?? 'system' }}: {{ $payment->reversal_reason }}. It no longer counts toward any invoice.</div>
    @elseif ($payment->isOpen())
        <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Not yet verified. This payment does <strong>not</strong> reduce the dealer's balance until an accountant checks it against the bank{{ $payment->method === 'cheque' && config('distributor.cheque_recognition') === 'on_clearance' ? ' and the cheque clears' : '' }}.
        </div>
    @elseif ($payment->status === 'verified' && ! $settled)
        <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Verified, but the cheque has not cleared yet. It will settle the invoices when the bank clears it (Cheque register).</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Details --}}
            <div class="card card-body">
                <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Dealer</dt><dd class="font-medium">{{ $payment->customer->name }} ({{ $payment->customer->code }})</dd></div>
                    <div><dt class="text-slate-500">Amount</dt><dd class="text-lg font-semibold">{{ money($payment->amount) }}</dd></div>
                    <div><dt class="text-slate-500">Payment date</dt><dd>{{ $payment->payment_date->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-slate-500">Method / channel</dt><dd>{{ $payment->methodLabel() }} · {{ \App\Models\Payment::CHANNELS[$payment->channel] ?? $payment->channel }}</dd></div>
                    @if ($payment->reference)<div><dt class="text-slate-500">Transaction reference</dt><dd class="font-mono">{{ $payment->reference }}</dd></div>@endif
                    @if ($payment->bankAccount)<div><dt class="text-slate-500">Into company account</dt><dd>{{ $payment->bankAccount->label() }}</dd></div>@endif
                    @if ($payment->method === 'cheque')
                        <div><dt class="text-slate-500">Cheque</dt><dd>No. <span class="font-mono">{{ $payment->cheque_number }}</span> · {{ $payment->bank?->name ?? $payment->cheque_bank }} {{ $payment->branch?->name }}</dd></div>
                        <div><dt class="text-slate-500">Cheque date</dt><dd>{{ $payment->cheque_date?->format('d/m/Y') }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">Cheque status</dt><dd>{{ \App\Models\Payment::CHEQUE_STATUSES[$payment->cheque_status] ?? '-' }}
                            @if ($payment->deposited_date) · deposited {{ $payment->deposited_date->format('d/m/Y') }} {{ $payment->deposit_reference ? '(ref '.$payment->deposit_reference.')' : '' }}@endif
                            @if ($payment->cleared_date) · cleared {{ $payment->cleared_date->format('d/m/Y') }}@endif
                            @if ($payment->returned_date) · returned {{ $payment->returned_date->format('d/m/Y') }}: {{ $payment->return_reason }}{{ $payment->bank_charges > 0 ? ' (charges '.money($payment->bank_charges).')' : '' }}@endif</dd></div>
                    @elseif ($payment->bank)
                        <div><dt class="text-slate-500">Sending bank</dt><dd>{{ $payment->bank->name }} {{ $payment->branch?->name }}</dd></div>
                    @endif
                    <div><dt class="text-slate-500">Entered by</dt><dd>{{ $payment->user?->name }} · {{ $payment->created_at->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="text-slate-500">Verified by</dt><dd>{{ $payment->verifier ? $payment->verifier->name.' · '.$payment->verified_at?->format('d/m/Y H:i') : '-' }}</dd></div>
                    @if ($payment->submission)<div><dt class="text-slate-500">From inbox item</dt><dd><a href="{{ route('payment-inbox.show', $payment->submission) }}" class="link">{{ $payment->submission->number }}</a></dd></div>@endif
                </dl>
                @if ($payment->notes)<p class="mt-3 whitespace-pre-line text-sm"><span class="text-slate-500">Notes:</span> {{ $payment->notes }}</p>@endif
            </div>

            {{-- Documents --}}
            <div class="card">
                <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Slips & documents</h2>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    @forelse ($payment->attachments as $doc)
                        <div class="rounded-md border border-slate-200 p-2 text-sm">
                            @if ($doc->isImage())
                                <a href="{{ route('attachments.show', $doc) }}" target="_blank"><img src="{{ route('attachments.show', $doc) }}" alt="{{ $doc->original_name }}" class="max-h-64 w-full rounded object-contain"></a>
                            @else
                                <iframe src="{{ route('attachments.show', $doc) }}" class="h-64 w-full rounded" title="{{ $doc->original_name }}"></iframe>
                            @endif
                            <div class="mt-1 flex justify-between gap-2">
                                <span class="truncate">{{ \App\Models\DocumentAttachment::KINDS[$doc->kind] ?? $doc->kind }} · {{ $doc->original_name }}</span>
                                <a href="{{ route('attachments.show', [$doc, 'download' => 1]) }}" class="link shrink-0">Download</a>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No slip attached.</p>
                    @endforelse
                </div>
                @can('payments.record')
                    <form method="POST" action="{{ route('payments.attachments.store', $payment) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2 border-t border-slate-200 px-5 py-3">
                        @csrf
                        <select name="kind" class="input w-44">@foreach (\App\Models\DocumentAttachment::KINDS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required class="text-sm">
                        <button class="btn btn-secondary btn-sm">Attach</button>
                    </form>
                @endcan
            </div>

            {{-- Allocations --}}
            <div class="card overflow-x-auto">
                <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Invoice allocation {{ $settled ? '' : '(proposed – not counted yet)' }}</h2>
                <table class="table">
                    <thead><tr><th>Invoice</th><th>Invoice date</th><th>Invoice balance</th><th class="num">Allocated</th></tr></thead>
                    <tbody>
                    @forelse ($payment->allocations as $a)
                        <tr>
                            <td><a href="{{ route('invoices.show', $a->invoice) }}" class="link">{{ $a->invoice->number }}</a></td>
                            <td>{{ $a->invoice->invoice_date->format('d/m/Y') }}</td>
                            <td>{{ money($a->invoice->balance(), false) }}</td>
                            <td class="num">{{ money($a->amount, false) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-slate-500">Not allocated to any invoice.</td></tr>
                    @endforelse
                    <tr class="font-medium"><td colspan="3" class="text-right">Unallocated</td><td class="num">{{ money($payment->unallocatedAmount(), false) }}</td></tr>
                    </tbody>
                </table>

                @php $mayAllocate = $payment->isOpen() ? $user->can('payments.record') : ($payment->status === 'verified' && $user->can('payments.allocate')); @endphp
                @if ($mayAllocate && $openInvoices->isNotEmpty())
                    <form method="POST" action="{{ route('payments.allocate', $payment) }}" class="border-t border-slate-200">
                        @csrf
                        <div class="px-5 pt-3 text-sm font-semibold">Allocate the unallocated {{ money($payment->unallocatedAmount()) }}</div>
                        <table class="table">
                            <tbody>
                            @foreach ($openInvoices as $inv)
                                <tr>
                                    <td>{{ $inv->number }} <span class="text-xs text-slate-500">due {{ $inv->due_date->format('d/m/Y') }}</span></td>
                                    <td class="num">{{ money($inv->balance(), false) }}</td>
                                    <td class="w-40"><input type="number" step="0.01" min="0" max="{{ $inv->balance() }}" name="allocations[{{ $inv->id }}]" class="input text-right"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="px-5 pb-3"><button class="btn btn-secondary btn-sm">Save allocation</button></div>
                    </form>
                @endif
                @if ($mayAllocate && $payment->allocations->isNotEmpty())
                    <form method="POST" action="{{ route('payments.reallocate', $payment) }}" class="flex flex-wrap items-end gap-2 border-t border-slate-200 px-5 py-3"
                          onsubmit="return confirm('Remove all allocations of this payment so it can be allocated again?')">
                        @csrf
                        <input name="reason" required placeholder="Reason for reallocation" class="input w-72">
                        <button class="btn btn-secondary btn-sm">Clear allocations</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            {{-- Actions --}}
            @if ($payment->isOpen() || $payment->status === 'verified')
                <div class="card card-body space-y-3">
                    <h2 class="font-semibold">Actions</h2>
                    @if ($payment->status === 'draft' && $user->can('payments.record'))
                        <form method="POST" action="{{ route('payments.action', [$payment, 'submit']) }}">@csrf<button class="btn btn-primary w-full">Submit for verification</button></form>
                    @endif

                    @if ($payment->isOpen() && $user->can('payments.verify'))
                        @if ($canVerify)
                            <p class="rounded-md bg-slate-50 p-2 text-sm text-slate-600">{{ $canVerify }}</p>
                        @else
                            <form method="POST" action="{{ route('payments.action', [$payment, 'verify']) }}" class="space-y-2"
                                  onsubmit="return confirm('Confirm you have checked this amount and reference in the company bank records?')">
                                @csrf
                                <input name="note" placeholder="Verification note, e.g. seen on BOC statement 15/10" class="input">
                                <button class="btn btn-success w-full">Verify against bank</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('payments.action', [$payment, 'request-info']) }}" class="space-y-2">
                            @csrf
                            <input name="note" required placeholder="What information is missing?" class="input">
                            <button class="btn btn-secondary w-full">Request more information</button>
                        </form>
                        <form method="POST" action="{{ route('payments.action', [$payment, 'reject']) }}" class="space-y-2" onsubmit="return confirm('Reject this payment?')">
                            @csrf
                            <input name="note" required placeholder="Reason for rejection" class="input">
                            <button class="btn btn-danger w-full">Reject</button>
                        </form>
                    @endif

                    @if ($payment->status === 'verified' && $user->can('payments.reverse'))
                        <form method="POST" action="{{ route('payments.action', [$payment, 'reverse']) }}" class="space-y-2" onsubmit="return confirm('Reverse this verified payment? Its invoices will be open again.')">
                            @csrf
                            <input name="note" required placeholder="Reason for reversal" class="input">
                            <button class="btn btn-danger w-full">Reverse payment</button>
                        </form>
                    @endif
                    @if ($payment->method === 'cheque')
                        <a href="{{ route('cheques.index', ['tab' => 'all', 'q' => $payment->cheque_number]) }}" class="btn btn-secondary w-full">Open in cheque register</a>
                    @endif
                </div>
            @endif

            <div class="card card-body text-sm">
                <h2 class="mb-2 font-semibold">Dealer</h2>
                <div class="flex justify-between"><span class="text-slate-500">Balance owing (verified money only)</span><span class="font-semibold">{{ money($balance) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Credit limit</span><span>{{ $payment->customer->credit_limit > 0 ? money($payment->customer->credit_limit) : 'No limit' }}</span></div>
                <h3 class="mt-4 mb-1 font-semibold">Previous payments</h3>
                @forelse ($previous as $p)
                    <div class="flex justify-between border-t border-slate-100 py-1">
                        <a href="{{ route('payments.show', $p) }}" class="link">{{ $p->number }}</a>
                        <span class="text-slate-500">{{ $p->payment_date->format('d/m/y') }}</span>
                        <span>{{ money($p->amount, false) }}</span>
                        <x-status :status="$p->status === 'verified' ? 'verified' : $p->statusLabel()" />
                    </div>
                @empty
                    <p class="text-slate-500">None.</p>
                @endforelse
            </div>

            <div class="card card-body text-sm">
                <h2 class="mb-2 font-semibold">History</h2>
                <ol class="space-y-2">
                    @foreach ($payment->history as $h)
                        <li class="border-l-2 border-slate-200 pl-3">
                            <div class="text-xs text-slate-500">{{ $h->created_at->format('d/m/Y H:i') }} · {{ $h->user?->name ?? 'System' }}</div>
                            <div>{{ $h->field === 'cheque_status' ? 'Cheque: ' : '' }}{{ $h->from ? str_replace('_', ' ', $h->from).' → ' : '' }}<strong>{{ str_replace('_', ' ', $h->to) }}</strong></div>
                            @if ($h->note)<div class="text-slate-600">{{ $h->note }}</div>@endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>
</x-layouts.app>
