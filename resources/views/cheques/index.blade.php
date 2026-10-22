<x-layouts.app title="Cheque register">
    <x-page-header title="Cheque register" subtitle="Dealer cheques: received → verified → deposited → cleared (or returned). A cheque settles invoices only when the bank clears it.">
        <x-export-buttons />
        <a href="{{ route('payments.index', ['method' => 'cheque']) }}" class="btn btn-secondary">All cheque payments</a>
    </x-page-header>

    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat label="In hand ({{ $summary['in_hand_count'] }})" :value="money($summary['in_hand'])" :href="route('cheques.index', ['tab' => 'in_hand'])" />
        <x-stat label="Due to deposit now ({{ $summary['due_count'] }})" :value="money($summary['due'])" :href="route('cheques.index', ['tab' => 'due'])" :tone="$summary['due'] > 0 ? 'amber' : 'slate'" />
        <x-stat label="Deposited, not cleared ({{ $summary['deposited_count'] }})" :value="money($summary['deposited'])" :href="route('cheques.index', ['tab' => 'deposited'])" />
    </div>

    <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
        @foreach (\App\Http\Controllers\ChequeController::TABS as $key => $label)
            <a href="{{ route('cheques.index', ['tab' => $key]) }}"
               class="-mb-px border-b-2 px-3 py-2 text-sm {{ $tab === $key ? 'border-indigo-600 font-medium text-indigo-700' : 'border-transparent text-slate-600 hover:text-slate-900' }}">{{ $label }}</a>
        @endforeach
    </div>

    <x-search-bar placeholder="Receipt, cheque no, bank or customer">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <x-field label="Cheque date from" name="from" type="date" :value="request('from')" class="w-40" />
        <x-field label="To" name="to" type="date" :value="request('to')" class="w-40" />
    </x-search-bar>

    @php $canDeposit = in_array($tab, ['in_hand', 'due']); @endphp
    <form method="POST" action="{{ route('cheques.deposit') }}" x-data="{ selected: [] }"
          onsubmit="return confirm('Deposit the selected cheques?')">
        @csrf
        @if ($canDeposit && auth()->user()->can('cheques.manage'))
            <div class="card card-body mb-4 flex flex-wrap items-end gap-3">
                <div class="text-sm"><span class="font-semibold" x-text="selected.length"></span> selected</div>
                <x-field label="Deposit date" name="deposited_date" type="date" :value="today()->format('Y-m-d')" required class="w-40" />
                <div class="w-72">
                    <label class="label">Deposit to company account</label>
                    <select name="company_bank_account_id" class="input" required>
                        <option value="">Select account...</option>
                        @foreach ($depositBanks as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                    @if ($depositBanks->isEmpty())<p class="error">Add a company bank account first (Setup → Company bank accounts).</p>@endif
                </div>
                <x-field label="Deposit slip ref" name="deposit_reference" class="w-40" />
                <button class="btn btn-primary" :disabled="!selected.length">Deposit selected</button>
            </div>
        @endif

        <div class="card overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    @if ($canDeposit)<th class="w-8"></th>@endif
                    <th>Receipt</th><th>Customer</th><th>Cheque no</th><th>Bank</th><th>Cheque date</th><th>Status</th><th>Deposit</th><th class="num">Amount</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($cheques as $p)
                    @php $days = \App\Http\Controllers\ChequeController::daysAfter($p); $status = $p->chequeStatus(); @endphp
                    <tr>
                        @if ($canDeposit)
                            <td><input type="checkbox" name="ids[]" value="{{ $p->id }}" x-model="selected" class="rounded border-slate-300"></td>
                        @endif
                        <td><a href="{{ route('payments.show', $p) }}" class="link">{{ $p->number }}</a><div class="text-xs text-slate-500">{{ $p->payment_date->format('d/m/Y') }}</div></td>
                        <td>{{ $p->customer->name }}</td>
                        <td class="font-medium">{{ $p->cheque_number }}</td>
                        <td>{{ $p->bank?->displayName() ?? $p->cheque_bank }}<div class="text-xs text-slate-500">{{ $p->branch?->name }}</div></td>
                        <td class="whitespace-nowrap">{{ $p->cheque_date?->format('d/m/Y') }}
                            @if ($status === 'in hand' && $days !== null)
                                <div class="text-xs {{ $days >= 0 ? 'text-amber-700' : 'text-slate-500' }}">{{ $days >= 0 ? ($days === 0 ? 'Due today' : $days.' day(s) past date') : abs($days).' day(s) to go' }}</div>
                            @endif
                        </td>
                        <td><x-status :status="$status" /><div class="mt-1"><x-status :status="$p->status === 'verified' ? 'verified' : $p->statusLabel()" /></div></td>
                        <td class="text-xs">{{ $p->deposited_date?->format('d/m/Y') }} {{ $p->deposit_bank }}
                            @if ($p->cleared_date)<div class="text-emerald-700">Cleared {{ $p->cleared_date->format('d/m/Y') }}</div>@endif
                            @if ($p->returned_date)<div class="text-red-700">Returned {{ $p->returned_date->format('d/m/Y') }}{{ $p->return_reason ? ': '.$p->return_reason : '' }}{{ $p->bank_charges > 0 ? ' · charges '.money($p->bank_charges, false) : '' }}</div>@endif
                            @if ($status === 'deposited' && $p->status !== 'verified')<div class="text-amber-700">Verify before clearing</div>@endif
                        </td>
                        <td class="num font-medium">{{ money($p->amount, false) }}</td>
                        <td class="whitespace-nowrap text-right" x-data="{ act: null }">
                            @if ($status === 'deposited' && $p->status === 'verified' && auth()->user()->can('cheques.clear'))
                                <button type="button" @click="act = act === 'clear' ? null : 'clear'" class="btn btn-success btn-sm">Cleared</button>
                            @endif
                            @if (in_array($status, ['in hand', 'deposited', 'cleared']) && ! in_array($p->status, ['rejected', 'reversed']) && auth()->user()->can('cheques.clear'))
                                <button type="button" @click="act = act === 'return' ? null : 'return'" class="btn btn-danger btn-sm">Returned</button>
                            @endif
                            {{-- Inline action forms live outside the deposit form (see below) and are submitted via their form ids. --}}
                            <div x-show="act === 'clear'" x-cloak class="mt-2 flex justify-end gap-1">
                                <input type="date" form="clear-{{ $p->id }}" name="cleared_date" value="{{ today()->format('Y-m-d') }}" class="input w-36">
                                <button form="clear-{{ $p->id }}" class="btn btn-success btn-sm">Save</button>
                            </div>
                            <div x-show="act === 'return'" x-cloak class="mt-2 flex flex-wrap justify-end gap-1">
                                <input type="date" form="return-{{ $p->id }}" name="returned_date" value="{{ today()->format('Y-m-d') }}" class="input w-36">
                                <input form="return-{{ $p->id }}" name="reason" required placeholder="Reason, e.g. insufficient funds" class="input w-52">
                                <input form="return-{{ $p->id }}" name="bank_charges" type="number" step="0.01" min="0" placeholder="Bank charges" class="input w-28">
                                <button form="return-{{ $p->id }}" class="btn btn-danger btn-sm">Save</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="py-6 text-center text-slate-500">No cheques here.</td></tr>
                @endforelse
                </tbody>
                @if ($count)
                    <tfoot><tr class="bg-slate-50 font-semibold"><td colspan="{{ $canDeposit ? 8 : 7 }}" class="px-3 py-2">{{ $count }} cheque(s)</td><td class="num px-3 py-2">{{ money($total, false) }}</td><td></td></tr></tfoot>
                @endif
            </table>
        </div>
    </form>

    {{-- Separate forms for clear / return (HTML forms cannot be nested inside the deposit form). --}}
    @foreach ($cheques as $p)
        <form id="clear-{{ $p->id }}" method="POST" action="{{ route('cheques.clear', $p) }}" class="hidden">@csrf</form>
        <form id="return-{{ $p->id }}" method="POST" action="{{ route('cheques.return', $p) }}" class="hidden"
              onsubmit="return confirm('Mark this cheque as returned? The invoices it paid will become unpaid again.')">@csrf</form>
    @endforeach

    <div class="mt-4">{{ $cheques->links() }}</div>
</x-layouts.app>
