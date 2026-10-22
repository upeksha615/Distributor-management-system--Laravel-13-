<x-layouts.app :title="$bank->displayName().' branches'">
    <x-page-header :title="$bank->name" subtitle="Branches">
        <a href="{{ route('banks.index') }}" class="btn btn-secondary">All banks</a>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 self-start">
            <form method="POST" action="{{ route('banks.branches.store', $bank) }}" class="card card-body space-y-3">
                @csrf
                <h2 class="font-semibold">Add branch</h2>
                <x-field label="Branch name" name="name" required />
                <x-field label="Branch code" name="code" />
                <x-field label="City" name="city" />
                <button class="btn btn-primary">Add branch</button>
            </form>
            <form method="POST" action="{{ route('banks.branches.import', $bank) }}" enctype="multipart/form-data" class="card card-body space-y-3">
                @csrf
                <h2 class="font-semibold">Import branches</h2>
                <p class="text-xs text-slate-500">Excel or CSV with columns <span class="font-mono">name</span>, <span class="font-mono">code</span>, <span class="font-mono">city</span>. Existing branch names are updated.</p>
                <input type="file" name="file" accept=".xlsx,.csv" required class="block w-full text-sm">
                <button class="btn btn-secondary">Import</button>
            </form>
        </div>

        <div class="lg:col-span-2">
            <form method="GET" class="mb-3 flex gap-2"><input type="search" name="q" value="{{ request('q') }}" placeholder="Search branch, code or city" class="input"><button class="btn btn-secondary">Search</button></form>
            <div class="card overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Branch</th><th>Code</th><th>City</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($branches as $branch)
                        <tr x-data="{ edit: false }" class="{{ $branch->is_active ? '' : 'text-slate-400' }}">
                            <td colspan="3" x-show="edit" x-cloak>
                                <form method="POST" action="{{ route('banks.branches.update', [$bank, $branch]) }}" class="flex flex-wrap gap-2">
                                    @csrf @method('PUT')
                                    <input name="name" value="{{ $branch->name }}" class="input w-48" required>
                                    <input name="code" value="{{ $branch->code }}" placeholder="Code" class="input w-24">
                                    <input name="city" value="{{ $branch->city }}" placeholder="City" class="input w-36">
                                    <x-checkbox label="Active" name="is_active" :checked="$branch->is_active" />
                                    <button class="btn btn-primary btn-sm">Save</button>
                                </form>
                            </td>
                            <td x-show="!edit">{{ $branch->name }}</td>
                            <td x-show="!edit">{{ $branch->code }}</td>
                            <td x-show="!edit">{{ $branch->city }}</td>
                            <td class="text-right"><button type="button" @click="edit = !edit" class="btn btn-secondary btn-sm" x-text="edit ? 'Close' : 'Edit'"></button></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-slate-500">No branches yet. Add them or import a list.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $branches->links() }}</div>
        </div>
    </div>
</x-layouts.app>
