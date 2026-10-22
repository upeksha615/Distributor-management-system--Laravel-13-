<x-layouts.app title="Banks & branches">
    <x-page-header title="Banks & branches" subtitle="Licensed banks from the Central Bank of Sri Lanka list (as at 31 May 2026). Add codes and branches as you need them.">
        <a href="{{ route('bank-accounts.index') }}" class="btn btn-secondary">Company bank accounts</a>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('banks.store') }}" class="card card-body space-y-3 self-start">
            @csrf
            <h2 class="font-semibold">Add a bank</h2>
            <p class="text-xs text-slate-500">Only if a bank is newly licensed or missing. Check the CBSL list first.</p>
            <x-field label="Official name" name="name" required />
            <x-field label="Short name" name="short_name" />
            <x-field label="Bank code" name="code" help="CEFTS / SLIPS code, if you use it." />
            <x-select label="Type" name="type" :options="\App\Models\Bank::TYPES" />
            <button class="btn btn-primary">Add bank</button>
        </form>

        <div class="lg:col-span-2">
            <form method="GET" class="mb-3 flex gap-2"><input type="search" name="q" value="{{ request('q') }}" placeholder="Search banks" class="input"><button class="btn btn-secondary">Search</button></form>
            <div class="card overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Bank</th><th>Code</th><th>Type</th><th class="num">Branches</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($banks as $bank)
                        <tr x-data="{ edit: false }" class="{{ $bank->is_active ? '' : 'text-slate-400' }}">
                            <td>
                                <div x-show="!edit"><span class="font-medium">{{ $bank->name }}</span>@if ($bank->short_name)<span class="text-slate-500"> · {{ $bank->short_name }}</span>@endif</div>
                                <form x-show="edit" x-cloak method="POST" action="{{ route('banks.update', $bank) }}" class="grid gap-2 sm:grid-cols-2">
                                    @csrf @method('PUT')
                                    <input name="name" value="{{ $bank->name }}" class="input sm:col-span-2" required>
                                    <input name="short_name" value="{{ $bank->short_name }}" placeholder="Short name" class="input">
                                    <input name="code" value="{{ $bank->code }}" placeholder="Bank code" class="input">
                                    <select name="type" class="input">@foreach (\App\Models\Bank::TYPES as $k => $v)<option value="{{ $k }}" @selected($bank->type === $k)>{{ $v }}</option>@endforeach</select>
                                    <x-checkbox label="Active" name="is_active" :checked="$bank->is_active" />
                                    <div class="sm:col-span-2"><button class="btn btn-primary btn-sm">Save</button></div>
                                </form>
                            </td>
                            <td>{{ $bank->code }}</td>
                            <td class="text-xs">{{ $bank->type === 'specialised' ? 'Specialised' : ucfirst($bank->type) }}</td>
                            <td class="num">{{ $bank->branches_count }}</td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('banks.branches', $bank) }}" class="btn btn-secondary btn-sm">Branches</a>
                                <button type="button" @click="edit = !edit" class="btn btn-secondary btn-sm" x-text="edit ? 'Close' : 'Edit'"></button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
