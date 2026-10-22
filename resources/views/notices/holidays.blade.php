<x-layouts.app title="Holidays">
    <x-page-header title="Holidays {{ $year }}" subtitle="Shown in the dashboard calendar. The 2026 list comes from the Central Bank of Sri Lanka; add next year's from the government gazette when it is published.">
        <a href="{{ route('notices.index') }}" class="btn btn-secondary">Notice board</a>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($years as $y)
            <a href="{{ route('holidays.index', ['year' => $y]) }}" class="btn btn-sm {{ $y == $year ? 'btn-primary' : 'btn-secondary' }}">{{ $y }}</a>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ route('holidays.store') }}" class="card card-body h-fit space-y-4">
            @csrf
            <h2 class="font-semibold">Add a holiday</h2>
            <x-field label="Date" name="date" type="date" required />
            <x-field label="Name" name="name" required maxlength="150" placeholder="e.g. Duruthu Full Moon Poya Day" />
            <div class="space-y-1">
                <x-checkbox label="Bank holiday" name="is_bank" :checked="true" />
                <x-checkbox label="Public holiday" name="is_public" :checked="true" />
                <x-checkbox label="Mercantile holiday" name="is_mercantile" />
            </div>
            <button class="btn btn-primary">Add holiday</button>
        </form>

        <div class="card overflow-x-auto xl:col-span-2">
            <table class="table">
                <thead><tr><th>Date</th><th>Day</th><th>Holiday</th><th>Type</th><th></th></tr></thead>
                <tbody>
                @forelse ($holidays as $h)
                    <tr class="{{ $h->date->isPast() && ! $h->date->isToday() ? 'text-slate-400' : '' }}">
                        <td class="whitespace-nowrap">{{ $h->date->format('d/m/Y') }}</td>
                        <td>{{ $h->date->format('l') }}</td>
                        <td>{{ $h->name }}</td>
                        <td class="text-xs">{{ $h->typesLabel() }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('holidays.destroy', $h) }}" onsubmit="return confirm('Remove {{ addslashes($h->name) }}?')">
                                @csrf @method('DELETE')
                                <button class="text-sm text-red-600 hover:underline">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">No holidays entered for {{ $year }}.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
