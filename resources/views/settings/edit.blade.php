<x-layouts.app title="Settings">
    <x-page-header title="Business settings" subtitle="Company details printed on documents, and the rules for credit, payments and approvals." />

    <form method="POST" action="{{ route('settings.update') }}" class="max-w-3xl space-y-4">
        @csrf @method('PUT')
        <div class="card card-body grid gap-4 sm:grid-cols-2">
            @foreach ($fields as $key => [$configKey, $label, $type, $help])
                @if ($key === 'allow_negative_stock')
                    <h2 class="border-t border-slate-200 pt-4 font-semibold sm:col-span-2">Business rules</h2>
                @elseif ($key === 'email_dealer_payment_notices')
                    <h2 class="border-t border-slate-200 pt-4 font-semibold sm:col-span-2">Email</h2>
                @endif

                @if ($type === 'bool')
                    <div class="sm:col-span-2">
                        <x-checkbox :label="$label" :name="$key" :checked="(bool) $values[$key]" />
                        @if ($help)<p class="ml-6 text-xs text-slate-500">{{ $help }}</p>@endif
                    </div>
                @elseif (str_starts_with($type, 'select:'))
                    <div class="sm:col-span-2">
                        <x-select :label="$label" :name="$key" :options="\App\Support\Settings::options($type)" :value="$values[$key]" />
                        @if ($help)<p class="mt-1 text-xs text-slate-500">{{ $help }}</p>@endif
                    </div>
                @elseif ($type === 'textarea')
                    <x-field :label="$label" :name="$key" type="textarea" :value="$values[$key]" :help="$help" class="sm:col-span-2" />
                @else
                    <x-field :label="$label" :name="$key" :type="in_array($type, ['number', 'money', 'days']) ? 'number' : 'text'" step="0.01" :value="$values[$key]" :help="$help"
                             :required="in_array($key, ['company_name', 'currency', 'tax_rate'])" />
                @endif
            @endforeach
        </div>
        <p class="text-sm text-slate-500">Tax rates and VAT treatment must be confirmed by your accountant against current Sri Lankan rules.</p>
        <button class="btn btn-primary">Save settings</button>
    </form>
</x-layouts.app>
