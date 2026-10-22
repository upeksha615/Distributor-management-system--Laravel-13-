<x-layouts.app title="Record dealer payment">
    <x-page-header title="Record dealer payment" subtitle="Saved payments wait for an accountant to verify them against the bank. They do not reduce the dealer's balance until then.">
        <a href="{{ route('payment-inbox.index') }}" class="btn btn-secondary">Slip inbox</a>
        <a href="{{ route('payments.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ route('payments.store') }}" enctype="multipart/form-data" class="space-y-4"
          x-data="{
              customerId: @js(old('customer_id', $customerId)),
              method: @js(old('method', 'bank_transfer')),
              mode: @js(old('allocation_mode', 'auto')),
              amount: @js(old('amount', '')),
              invoices: [], balance: null, pending: null,
              alloc: @js(old('allocations', (object) [])),
              async load() {
                  this.invoices = []; this.balance = null;
                  if (!this.customerId) return;
                  const res = await fetch('{{ url('customers') }}/' + this.customerId + '/open-invoices', { headers: { Accept: 'application/json' } });
                  const data = await res.json();
                  this.invoices = data.invoices; this.balance = data.balance; this.pending = data.pending;
              },
              get allocated() { return Object.values(this.alloc).reduce((s, v) => s + (Number(v) || 0), 0); },
              fillOldestFirst() {
                  let left = Number(this.amount) || 0; this.alloc = {};
                  for (const inv of this.invoices) { const a = Math.min(left, inv.balance); if (a > 0) { this.alloc[inv.id] = a.toFixed(2); left -= a; } }
              },
              money(v) { return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
          }"
          x-init="load()">
        @csrf
        <div class="card card-body grid gap-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="label">Dealer <span class="text-red-500">*</span></label>
                <select name="customer_id" x-model="customerId" @change="alloc = {}; load()" class="input" required>
                    <option value="">Select dealer...</option>
                    @foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }})</option>@endforeach
                </select>
                <p class="mt-1 text-sm" x-show="balance !== null">
                    Balance owing: <strong x-text="'{{ config('distributor.currency') }} ' + money(balance)"></strong>
                    <span x-show="pending > 0" class="text-amber-700"> · <span x-text="money(pending)"></span> already awaiting verification</span>
                </p>
                @error('customer_id')<p class="error">{{ $message }}</p>@enderror
            </div>
            <x-field label="Payment / transfer date" name="payment_date" type="date" :value="today()->format('Y-m-d')" required />
            <div>
                <label class="label">Amount (LKR) <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" x-model="amount" class="input text-right" required>
                @error('amount')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Method</label>
                <select name="method" x-model="method" class="input">
                    @foreach (\App\Models\Payment::METHODS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>

            {{-- Bank transfer / deposit --}}
            <template x-if="method === 'bank_transfer' || method === 'bank_deposit'">
                <div class="contents">
                    <div class="sm:col-span-2">
                        <label class="label">Received into company account <span class="text-red-500">*</span></label>
                        <select name="company_bank_account_id" class="input" required>
                            <option value="">Select account...</option>
                            @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('company_bank_account_id') == $a->id)>{{ $a->label() }}</option>@endforeach
                        </select>
                        @if ($accounts->isEmpty())<p class="error">No company bank accounts set up yet (Setup → Company bank accounts).</p>@endif
                    </div>
                    <x-field label="Transaction / deposit reference" name="reference" required />
                </div>
            </template>
            <template x-if="method === 'bank_transfer'">
                <div class="contents">@include('partials.bank-picker', ['bankLabel' => 'Sending bank (dealer\'s bank)'])</div>
            </template>

            {{-- Cheque --}}
            <template x-if="method === 'cheque'">
                <div class="contents">
                    <x-field label="Cheque number" name="cheque_number" required />
                    @include('partials.bank-picker', ['bankLabel' => 'Cheque bank', 'required' => true])
                    <x-field label="Cheque date" name="cheque_date" type="date" required help="Post-dated cheques: the date written on the cheque." />
                </div>
            </template>

            <template x-if="method === 'cash' || method === 'card'">
                <x-field label="Reference" name="reference" help="Card slip number, cash receipt book number..." class="sm:col-span-3" />
            </template>

            <div class="sm:col-span-2">
                <label class="label">Payment slip / cheque image</label>
                <input type="file" name="slip" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:px-3 file:py-1.5">
                <p class="mt-1 text-xs text-slate-500">PDF, JPG or PNG up to {{ config('distributor.max_upload_mb') }} MB. Stored privately. A slip is evidence, not proof of clearance.</p>
                @error('slip')<p class="error">{{ $message }}</p>@enderror
            </div>
            <x-field label="Notes" name="notes" class="sm:col-span-2" />
        </div>

        <div class="card">
            <div class="flex flex-wrap items-center gap-5 border-b border-slate-200 px-5 py-3 text-sm">
                <span class="font-semibold">Proposed allocation</span>
                <label class="flex items-center gap-1.5"><input type="radio" name="allocation_mode" value="auto" x-model="mode"> Oldest invoices first</label>
                <label class="flex items-center gap-1.5"><input type="radio" name="allocation_mode" value="manual" x-model="mode"> Choose invoices</label>
                <label class="flex items-center gap-1.5"><input type="radio" name="allocation_mode" value="none" x-model="mode"> Unallocated / advance</label>
            </div>
            <div class="overflow-x-auto" x-show="mode === 'manual'" x-cloak>
                <table class="table">
                    <thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th class="num">Days late</th><th class="num">Balance</th><th class="num">Already claimed</th><th class="w-40 num">Apply</th></tr></thead>
                    <tbody>
                    <template x-for="inv in invoices" :key="inv.id">
                        <tr>
                            <td x-text="inv.number"></td>
                            <td x-text="inv.invoice_date"></td>
                            <td x-text="inv.due_date"></td>
                            <td class="num" :class="inv.overdue ? 'text-red-600' : ''" x-text="inv.overdue || '-'"></td>
                            <td class="num" x-text="money(inv.balance)"></td>
                            <td class="num text-amber-700" x-text="inv.pending ? money(inv.pending) : ''"></td>
                            <td><input type="number" step="0.01" min="0" :max="inv.balance" :name="`allocations[${inv.id}]`" x-model="alloc[inv.id]" class="input text-right"></td>
                        </tr>
                    </template>
                    <tr x-show="invoices.length === 0"><td colspan="7" class="py-4 text-center text-slate-500">No open invoices for this dealer.</td></tr>
                    </tbody>
                </table>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3 text-sm">
                    <button type="button" @click="fillOldestFirst()" class="btn btn-secondary btn-sm">Fill oldest first</button>
                    <div>Allocated <strong x-text="money(allocated)"></strong> of <strong x-text="money(amount)"></strong>
                        <span x-show="allocated > Number(amount) + 0.001" class="ml-2 font-semibold text-red-600">More than the payment amount</span>
                        <span x-show="allocated < Number(amount) - 0.001" class="ml-2 text-amber-700">Remainder stays unallocated</span>
                    </div>
                </div>
            </div>
            <p class="px-5 py-3 text-sm text-slate-500">Allocations are proposals until the payment is verified{{ config('distributor.cheque_recognition') === 'on_clearance' ? ' (and, for cheques, cleared by the bank)' : '' }}.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button name="save_as" value="pending_verification" class="btn btn-primary">Submit for verification</button>
            <button name="save_as" value="draft" class="btn btn-secondary">Save as draft</button>
        </div>
    </form>
</x-layouts.app>
