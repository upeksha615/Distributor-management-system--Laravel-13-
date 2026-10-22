<x-layouts.app title="Make supplier payment">
    <x-page-header title="Make supplier payment">
        <a href="{{ route('supplier-payments.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ route('supplier-payments.store') }}" class="space-y-4"
          x-data="{
              supplierId: @js(old('supplier_id', $supplierId)),
              method: @js(old('method', 'bank_transfer')),
              mode: @js(old('allocation_mode', 'auto')),
              amount: @js(old('amount', '')),
              bills: [],
              balance: null,
              alloc: @js(old('allocations', (object) [])),
              async load() {
                  this.bills = []; this.balance = null;
                  if (!this.supplierId) return;
                  const res = await fetch('{{ url('suppliers') }}/' + this.supplierId + '/open-bills', { headers: { Accept: 'application/json' } });
                  const data = await res.json();
                  this.bills = data.bills; this.balance = data.balance;
              },
              get allocated() { return Object.values(this.alloc).reduce((s, v) => s + (Number(v) || 0), 0); },
              fillOldestFirst() {
                  let left = Number(this.amount) || 0; this.alloc = {};
                  for (const b of this.bills) { const a = Math.min(left, b.balance); if (a > 0) { this.alloc[b.id] = a.toFixed(2); left -= a; } }
              },
              money(v) { return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
          }"
          x-init="load()">
        @csrf
        <div class="card card-body grid gap-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="label">Supplier <span class="text-red-500">*</span></label>
                <select name="supplier_id" x-model="supplierId" @change="alloc = {}; load()" class="input" required>
                    <option value="">Select supplier...</option>
                    @foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->code }})</option>@endforeach
                </select>
                @error('supplier_id')<p class="error">{{ $message }}</p>@enderror
                <p class="mt-1 text-sm" x-show="balance !== null">We owe: <strong x-text="'{{ config('distributor.currency') }} ' + money(balance)"></strong></p>
            </div>
            <x-field label="Payment date" name="payment_date" type="date" :value="today()->format('Y-m-d')" required />
            <div>
                <label class="label">Amount <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" x-model="amount" class="input text-right" required>
                @error('amount')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Method</label>
                <select name="method" x-model="method" class="input">
                    @foreach (\App\Models\Payment::METHODS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <template x-if="method === 'cheque'">
                <div class="contents">
                    <x-field label="Cheque number" name="cheque_number" required />
                    <x-field label="Cheque date" name="cheque_date" type="date" required help="Post-dated cheques: enter the date on the cheque." />
                    <x-field label="Reference" name="reference" />
                </div>
            </template>
            <template x-if="method !== 'cheque'">
                <x-field label="Reference" name="reference" help="Bank ref, TT no, etc." class="sm:col-span-3" />
            </template>
            <x-field label="Notes" name="notes" class="sm:col-span-4" />
        </div>

        <div class="card">
            <div class="flex flex-wrap items-center gap-5 border-b border-slate-200 px-5 py-3 text-sm">
                <span class="font-semibold">Apply payment to</span>
                <label class="flex items-center gap-1.5"><input type="radio" name="allocation_mode" value="auto" x-model="mode"> Oldest bills first</label>
                <label class="flex items-center gap-1.5"><input type="radio" name="allocation_mode" value="manual" x-model="mode"> Choose bills</label>
                <label class="flex items-center gap-1.5"><input type="radio" name="allocation_mode" value="none" x-model="mode"> Keep as advance / on account</label>
            </div>
            @error('allocations')<p class="error px-5 pt-2">{{ $message }}</p>@enderror
            <div class="overflow-x-auto" x-show="mode === 'manual'" x-cloak>
                <table class="table">
                    <thead><tr><th>Bill</th><th>Supplier inv</th><th>Description</th><th>Date</th><th>Due</th><th class="num">Days late</th><th class="num">Balance</th><th class="w-40 num">Apply</th></tr></thead>
                    <tbody>
                    <template x-for="b in bills" :key="b.id">
                        <tr>
                            <td x-text="b.number"></td>
                            <td x-text="b.supplier_invoice_no || '-'"></td>
                            <td class="text-slate-600" x-text="b.description"></td>
                            <td x-text="b.bill_date"></td>
                            <td x-text="b.due_date"></td>
                            <td class="num" :class="b.overdue ? 'text-red-600' : ''" x-text="b.overdue || '-'"></td>
                            <td class="num" x-text="money(b.balance)"></td>
                            <td><input type="number" step="0.01" min="0" :max="b.balance" :name="`allocations[${b.id}]`" x-model="alloc[b.id]" class="input text-right"></td>
                        </tr>
                    </template>
                    <tr x-show="bills.length === 0"><td colspan="8" class="py-4 text-center text-slate-500">No open bills for this supplier.</td></tr>
                    </tbody>
                </table>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3 text-sm">
                    <button type="button" @click="fillOldestFirst()" class="btn btn-secondary btn-sm">Fill oldest first</button>
                    <div>Allocated <strong x-text="money(allocated)"></strong> of <strong x-text="money(amount)"></strong>
                        <span x-show="allocated > Number(amount) + 0.001" class="ml-2 font-semibold text-red-600">More than the payment amount</span>
                        <span x-show="allocated < Number(amount) - 0.001" class="ml-2 text-amber-700">Remainder stays on account</span>
                    </div>
                </div>
            </div>
            <p x-show="mode === 'auto'" class="px-5 py-3 text-sm text-slate-500">The amount is applied to the oldest due bills first. Any excess stays on account as an advance to the supplier.</p>
            <p x-show="mode === 'none'" x-cloak class="px-5 py-3 text-sm text-slate-500">The money is recorded as an unallocated advance. You can apply it to bills later from the payment's page.</p>
        </div>

        <button class="btn btn-primary">Save payment</button>
    </form>
</x-layouts.app>
