<x-layouts.app title="Notice board">
    <x-page-header title="Notice board" subtitle="Short messages shown to every user on the dashboard between the start and end dates.">
        <a href="{{ route('holidays.index') }}" class="btn btn-secondary">Holidays</a>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ $editing ? route('notices.update', $editing) : route('notices.store') }}" class="card card-body h-fit space-y-4">
            @csrf
            @if ($editing) @method('PUT') @endif
            <h2 class="font-semibold">{{ $editing ? 'Edit notice' : 'Post a notice' }}</h2>
            <x-field label="Title" name="title" :value="$editing?->title" required maxlength="150" />
            <x-field label="Message" name="body" type="textarea" :value="$editing?->body" maxlength="2000" />
            <x-select label="Type" name="level" :options="\App\Models\Notice::LEVELS" :value="$editing?->level ?? 'info'" required />
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Show from" name="starts_on" type="date" :value="($editing?->starts_on ?? today())->toDateString()" required />
                <x-field label="Until (optional)" name="ends_on" type="date" :value="$editing?->ends_on?->toDateString()" />
            </div>
            <div class="flex gap-2">
                <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Post notice' }}</button>
                @if ($editing)<a href="{{ route('notices.index') }}" class="btn btn-secondary">Cancel</a>@endif
            </div>
        </form>

        <div class="card overflow-x-auto xl:col-span-2">
            <table class="table">
                <thead><tr><th>Notice</th><th>Shows</th><th>Status</th><th>By</th><th></th></tr></thead>
                <tbody>
                @forelse ($notices as $n)
                    <tr>
                        <td>
                            <div class="font-medium text-slate-800">@if ($n->level === 'important')<span class="badge mr-1 bg-rose-100 text-rose-700">Important</span>@endif{{ $n->title }}</div>
                            @if ($n->body)<div class="max-w-md text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($n->body, 120) }}</div>@endif
                        </td>
                        <td class="whitespace-nowrap text-sm">{{ $n->starts_on->format('d/m/Y') }} – {{ $n->ends_on?->format('d/m/Y') ?? 'no end' }}</td>
                        <td>
                            @if ($n->isCurrent())<span class="badge bg-green-100 text-green-800">Showing</span>
                            @elseif ($n->starts_on->isFuture())<span class="badge bg-sky-100 text-sky-800">Scheduled</span>
                            @else<span class="badge bg-slate-100 text-slate-600">Ended</span>@endif
                        </td>
                        <td class="text-sm">{{ $n->user?->name }}</td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('notices.index', ['edit' => $n->id]) }}" class="link text-sm">Edit</a>
                            <form method="POST" action="{{ route('notices.destroy', $n) }}" class="inline" onsubmit="return confirm('Remove this notice?')">
                                @csrf @method('DELETE')
                                <button class="ml-2 text-sm text-red-600 hover:underline">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">No notices yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="p-4">{{ $notices->links() }}</div>
        </div>
    </div>
</x-layouts.app>
