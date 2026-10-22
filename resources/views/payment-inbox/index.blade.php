<x-layouts.app title="Payment slip inbox">
    <x-page-header title="Payment slip inbox" subtitle="Slips received by upload, email, WhatsApp or the dealer portal. Match each to a dealer and create the payment for verification.">
        <a href="{{ route('payment-inbox.create') }}" class="btn btn-primary">Upload slip</a>
        <a href="{{ route('payments.create') }}" class="btn btn-secondary">Record payment</a>
    </x-page-header>

    <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Open slips" :value="$counts['open']" :href="route('payment-inbox.index')" :tone="$counts['open'] ? 'amber' : 'slate'" />
        <x-stat label="Not matched to a dealer" :value="$counts['unmatched']" :tone="$counts['unmatched'] ? 'red' : 'slate'" />
        <x-stat label="Payments awaiting verification" :value="$counts['pending_verification']" :href="route('payments.index', ['status' => 'awaiting'])" />
        <x-stat label="Possible duplicates" :value="$counts['flagged']" :tone="$counts['flagged'] ? 'red' : 'slate'" />
    </div>

    <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
        @foreach (['open' => 'Open', 'mine' => 'Assigned to me', 'converted' => 'Payment created', 'rejected' => 'Rejected / duplicate', 'all' => 'All'] as $key => $label)
            <a href="{{ route('payment-inbox.index', ['tab' => $key]) }}" class="-mb-px border-b-2 px-3 py-2 text-sm {{ $tab === $key ? 'border-indigo-600 font-medium text-indigo-700' : 'border-transparent text-slate-600 hover:text-slate-900' }}">{{ $label }}</a>
        @endforeach
    </div>

    <x-search-bar placeholder="Slip no, reference, sender or dealer">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <x-select label="Channel" name="channel" :options="\App\Models\PaymentSubmission::CHANNELS" :value="request('channel')" placeholder="All" class="w-40" />
    </x-search-bar>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Slip</th><th>Received</th><th>Channel / sender</th><th>Dealer</th><th>Reference</th><th class="num">Claimed</th><th>Assigned</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($items as $s)
                <tr>
                    <td><a href="{{ route('payment-inbox.show', $s) }}" class="link">{{ $s->number }}</a>
                        <div class="text-xs text-slate-500">{{ $s->attachments_count }} file(s)</div></td>
                    <td class="whitespace-nowrap">{{ $s->received_at->format('d/m/Y H:i') }}<div class="text-xs text-slate-500">{{ $s->received_at->diffForHumans() }}</div></td>
                    <td>{{ \App\Models\PaymentSubmission::CHANNELS[$s->channel] ?? $s->channel }}<div class="text-xs text-slate-500">{{ $s->sender }}</div></td>
                    <td>{!! $s->customer ? e($s->customer->name) : '<span class="font-medium text-red-600">Not matched</span>' !!}</td>
                    <td class="font-mono text-xs">{{ $s->claimed_reference }}</td>
                    <td class="num">{{ $s->claimed_amount ? money($s->claimed_amount, false) : '-' }}</td>
                    <td>{{ $s->assignee?->name }}</td>
                    <td><x-status :status="strtolower(\App\Models\PaymentSubmission::STATUSES[$s->status])" />
                        @if ($s->payment)<div class="text-xs"><a href="{{ route('payments.show', $s->payment) }}" class="link">{{ $s->payment->number }}</a></div>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-slate-500">Nothing here.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $items->links() }}</div>

    <div class="card card-body mt-6 text-sm text-slate-600">
        <strong>Email and WhatsApp:</strong> automatic collection needs the company mailbox / WhatsApp Business credentials (see docs/INTEGRATIONS.md).
        Until they are configured, save slips received by email or WhatsApp here with <em>Upload slip</em> and choose the channel.
    </div>
</x-layouts.app>
