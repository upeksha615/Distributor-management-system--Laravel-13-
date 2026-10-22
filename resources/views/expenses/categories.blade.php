<x-layouts.app title="Expense categories">
    <x-page-header title="Expense categories">
        <a href="{{ route('expenses.index') }}" class="btn btn-secondary">Expenses</a>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('expense-categories.store') }}" class="card card-body space-y-3 self-start">
            @csrf
            <h2 class="font-semibold">Add category</h2>
            <x-field label="Name" name="name" required />
            <button class="btn btn-primary">Add</button>
        </form>

        <div class="card lg:col-span-2">
            <table class="table">
                <thead><tr><th>Name</th><th class="num">Expenses</th><th class="w-64"></th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr x-data="{ editing: false }">
                        <td>
                            <span x-show="!editing">{{ $row->name }}</span>
                            <form x-show="editing" x-cloak method="POST" action="{{ route('expense-categories.update', $row) }}" class="flex gap-2">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $row->name }}" class="input" required>
                                <button class="btn btn-primary btn-sm">Save</button>
                            </form>
                        </td>
                        <td class="num">{{ $row->expenses_count }}</td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" @click="editing = !editing" class="btn btn-secondary btn-sm" x-text="editing ? 'Cancel' : 'Rename'"></button>
                            @if ($row->expenses_count === 0)
                                <form method="POST" action="{{ route('expense-categories.destroy', $row) }}" class="inline" onsubmit="return confirm('Delete {{ addslashes($row->name) }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-6 text-center text-slate-500">None yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
