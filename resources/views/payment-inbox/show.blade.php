<x-layouts.app :title="$s->number">
    <x-page-header :title="'Slip '.$s->number" :subtitle="(\App\Models\PaymentSubmission::CHANNELS[$s->channel] ?? $s->channel).' · received '.$s->received_at->format('d/m/Y H:i').($s->sender ? ' · from '.$s->sender : '')">
        <x-status :status="strtolower(\App\Models\PaymentSubmission::STATUSES[$s->status])" class="self-center" />
        <a href="{{ route('payment-inbox.index') }}" class="btn btn-secondary">Inbox</a>
    </x-page-header>

    @foreach ($warnings as $w)
        <div class="mb-2 rounded-md border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-800"><strong>Possible duplicate:</strong> {{ $w }}</div>
    @endforeach
    @if ($s->payment)
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">Payment <a href="{{ route('payments.show', $s->payment) }}" class="link font-semibold">{{ $s->payment->number }}</a> was created from this slip ({{ $s->payment->statusLabel() }}).</div>
    @endif
    @if ($s->rejection_reason)
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $s->status === 'duplicate' ? 'Duplicate' : 'Rejected' }}: {{ $s->rejection_reason }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- Evidence --}}
        <div class="space-y-4 lg:col-span-3">
            @forelse ($s->attachments as $doc)
                <div class="card p-2">
                    @if ($doc->isImage())
                        <a href="{{ route('attachments.show', $doc) }}" target="_blank"><img src="{{ route('attachments.show', $doc) }}" alt="{{ $doc->original_name }}" class="max-h-[32rem] w-full rounded object-contain"></a>
                    @else
                        <iframe src="{{ route('attachments.show', $doc) }}" class="h-[32rem] w-full rounded" title="{{ $doc->original_name }}"></iframe>
                    @endif
                    <div class="mt-1 flex justify-between px-1 text-sm"><span class="truncate">{{ $doc->original_name }}</span><a href="{{ route('attachments.show', [$doc, 'download' => 1]) }}" class="link">Download</a></div>
                </div>
            @empty
                <div class="card card-body text-sm text-slate-500">No file attached.</div>
            @endforelse

            <div class="card card-body text-sm">
                <h2 class="mb-2 font-semibold">What the dealer claims</h2>
                <dl class="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                    <dt class="text-slate-500">Amount</dt><dd>{{ $s->claimed_amount ? money($s->claimed_amount) : '-' }}</dd>
                    <dt class="text-slate-500">Date</dt><dd>{{ $s->claimed_date?->format('d/m/Y') ?? '-' }}</dd>
                    <dt class="text-slate-500">Reference</dt><dd class="font-mono">{{ $s->claimed_reference ?: '-' }}</dd>
                    <dt class="text-slate-500">Method</dt><dd>{{ \App\Models\Payment::METHODS[$s->claimed_method] ?? '-' }}</dd>
                    <dt class="text-slate-500">Sending bank</dt><dd>{{ $s->bank?->name ?? '-' }}</dd>
                    <dt class="text-slate-500">Into account</dt><dd>{{ $s->bankAccount?->label() ?? '-' }}</dd>
                    <dt class="text-slate-500">Invoices mentioned</dt><dd>{{ $s->invoice_refs ?: '-' }}</dd>
                    <dt class="text-slate-500">Saved by</dt><dd>{{ $s->creator?->name ?? 'Automatic' }}</dd>
                </dl>
                @if ($s->subject || $s->message)<div class="mt-3 rounded bg-slate-50 p-2"><div class="font-medium">{{ $s->subject }}</div><div class="whitespace-pre-line">{{ $s->message }}</div></div>@endif
                @if ($s->notes)<p class="mt-3 whitespace-pre-line"><span class="text-slate-500">Notes:</span> {{ $s->notes }}</p>@endif
            </div>
        </div>

        {{-- Processing --}}
        <div class="space-y-4 lg:col-span-2">
            @if ($s->isOpen())
                <form method="POST" action="{{ route('payment-inbox.action', [$s, 'assign']) }}" class="card card-body flex items-end gap-2">
                    @csrf
                    <x-select label="Assigned to" name="assigned_to" :options="$staff" :value="$s->assigned_to" placeholder="Nobody" class="flex-1" />
                    <button class="btn btn-secondary btn-sm">Save</button>
                </form>

                <form method="POST" action="{{ route('payment-inbox.match', $s) }}" class="card card-body flex items-end gap-2">
                    @csrf
                    <x-select label="1. Match dealer" name="customer_id" :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name.' ('.$c->code.')'])" :value="$s->customer_id" placeholder="Select dealer..." class="flex-1" required />
                    <button class="btn btn-secondary btn-sm">Match</button>
                </form>

                @if ($s->customer)
                    <form method="POST" action="{{ route('payment-inbox.convert', $s) }}" class="card card-body space-y-3" x-data="{ method: @js(old('method', $s->claimed_method ?: 'bank_transfer')) }">
                        @csrf
                        <h2 class="font-semibold">2. Create payment for verification</h2>
                        <input type="hidden" name="customer_id" value="{{ $s->customer_id }}">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-field label="Amount (LKR)" name="amount" type="number" step="0.01" min="0.01" :value="$s->claimed_amount" required />
                            <x-field label="Payment date" name="payment_date" type="date" :value="($s->claimed_date ?? $s->received_at)->format('Y-m-d')" required />
                            <div>
                                <label class="label">Method</label>
                                <select name="method" x-model="method" class="input">@foreach (\App\Models\Payment::METHODS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                            </div>
                            <template x-if="method !== 'cheque'"><x-field label="Transaction reference" name="reference" :value="$s->claimed_reference" /></template>
                            <template x-if="method === 'cheque'"><x-field label="Cheque number" name="cheque_number" :value="$s->claimed_reference" required /></template>
                            <template x-if="method === 'cheque'"><x-field label="Cheque date" name="cheque_date" type="date" required /></template>
                            <template x-if="method === 'bank_transfer' || method === 'bank_deposit'">
                                <div class="sm:col-span-2">
                                    <x-select label="Into company account" name="company_bank_account_id" :options="$accounts->mapWithKeys(fn ($a) => [$a->id => $a->label()])" :value="$s->company_bank_account_id" placeholder="Select..." required />
                                </div>
                            </template>
                            <div class="sm:col-span-2">
                                <x-select label="Bank (sending / cheque)" name="bank_id" :options="$banks->pluck('name', 'id')" :value="$s->bank_id" placeholder="-" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-select label="Allocation" name="allocation_mode" :options="['auto' => 'Propose oldest invoices first', 'none' => 'Leave unallocated (allocate after verification)']" />
                            </div>
                        </div>
                        <button class="btn btn-primary w-full">Create payment & send for verification</button>
                    </form>
                @endif

                <div class="card card-body space-y-2">
                    <form method="POST" action="{{ route('payment-inbox.action', [$s, 'request-info']) }}" class="flex gap-2">@csrf
                        <input name="note" required placeholder="What is missing (e.g. clearer slip, reference)" class="input"><button class="btn btn-secondary btn-sm shrink-0">Request info</button></form>
                    <form method="POST" action="{{ route('payment-inbox.action', [$s, 'duplicate']) }}" class="flex gap-2" onsubmit="return confirm('Mark as duplicate?')">@csrf
                        <input name="note" required placeholder="Duplicate of..." class="input"><button class="btn btn-danger btn-sm shrink-0">Duplicate</button></form>
                    <form method="POST" action="{{ route('payment-inbox.action', [$s, 'reject']) }}" class="flex gap-2" onsubmit="return confirm('Reject this slip?')">@csrf
                        <input name="note" required placeholder="Reason" class="input"><button class="btn btn-danger btn-sm shrink-0">Reject</button></form>
                </div>
            @endif

            <div class="card card-body text-sm" x-data="{ copied: false }">
                <h2 class="mb-1 font-semibold">Acknowledgement to the dealer</h2>
                <p class="rounded bg-slate-50 p-2" x-ref="msg">{{ $acknowledgement }}</p>
                <button type="button" class="btn btn-secondary btn-sm mt-2" @click="navigator.clipboard.writeText($refs.msg.innerText); copied = true" x-text="copied ? 'Copied' : 'Copy text'"></button>
                <p class="mt-1 text-xs text-slate-500">Automatic sending needs email / WhatsApp to be configured.</p>
            </div>

            @if ($previous->isNotEmpty())
                <div class="card card-body text-sm">
                    <h2 class="mb-1 font-semibold">{{ $s->customer->name }}: recent payments</h2>
                    @foreach ($previous as $p)
                        <div class="flex justify-between border-t border-slate-100 py-1"><a href="{{ route('payments.show', $p) }}" class="link">{{ $p->number }}</a><span>{{ $p->payment_date->format('d/m/y') }}</span><span>{{ money($p->amount, false) }}</span><span class="text-xs">{{ $p->statusLabel() }}</span></div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
