<x-layouts.app :title="$meta['title']">
    <x-page-header :title="$meta['title']" :subtitle="$type === 'brands' ? 'Each brand belongs to a category. Products are added under a brand.' : 'Top level of the catalogue: Category → Brand → Product.'">
        <a href="{{ route('lookups.index', $type === 'brands' ? 'categories' : 'brands') }}" class="btn btn-secondary">{{ $type === 'brands' ? 'Categories' : 'Brands' }}</a>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('lookups.store', $type) }}" class="card card-body space-y-3 self-start">
            @csrf
            <h2 class="font-semibold">Add {{ strtolower($meta['singular']) }}</h2>
            @if ($type === 'brands')
                <x-select label="Category" name="category_id" :options="$categories->pluck('name', 'id')" :value="request('category_id')" placeholder="Select category..." required />
                @if ($categories->isEmpty())<p class="error">Create a category first.</p>@endif
            @endif
            <x-field label="Name" name="name" required />
            <button class="btn btn-primary">Add</button>
        </form>

        <div class="lg:col-span-2">
            @if ($type === 'brands')
                <form method="GET" class="mb-3 flex gap-2">
                    <select name="category_id" class="input w-56" onchange="this.form.submit()">
                        <option value="">All categories</option>
                        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                </form>
            @endif
            <div class="card">
                <table class="table">
                    <thead><tr>@if ($type === 'brands')<th>Category</th>@endif<th>Name</th>@if ($type === 'categories')<th class="num">Brands</th>@endif<th class="num">Products</th><th class="w-64"></th></tr></thead>
                    <tbody>
                    @forelse ($rows as $row)
                        <tr x-data="{ editing: false }">
                            @if ($type === 'brands')
                                <td>{!! $row->category ? e($row->category->name) : '<span class="text-red-600">No category</span>' !!}</td>
                            @endif
                            <td>
                                <span x-show="!editing">{{ $row->name }}</span>
                                <form x-show="editing" x-cloak method="POST" action="{{ route('lookups.update', [$type, $row->id]) }}" class="flex flex-wrap gap-2">
                                    @csrf @method('PUT')
                                    @if ($type === 'brands')
                                        <select name="category_id" class="input w-40" required>
                                            @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($row->category_id === $c->id)>{{ $c->name }}</option>@endforeach
                                        </select>
                                    @endif
                                    <input name="name" value="{{ $row->name }}" class="input w-44" required>
                                    <button class="btn btn-primary btn-sm">Save</button>
                                </form>
                            </td>
                            @if ($type === 'categories')<td class="num"><a href="{{ route('lookups.index', ['brands', 'category_id' => $row->id]) }}" class="link">{{ $row->brands_count }}</a></td>@endif
                            <td class="num"><a href="{{ route('products.index', [$type === 'brands' ? 'brand_id' : 'category_id' => $row->id]) }}" class="link">{{ $row->products_count }}</a></td>
                            <td class="text-right whitespace-nowrap">
                                <button type="button" @click="editing = !editing" class="btn btn-secondary btn-sm" x-text="editing ? 'Cancel' : 'Edit'"></button>
                                <form method="POST" action="{{ route('lookups.destroy', [$type, $row->id]) }}" class="inline" onsubmit="return confirm('Delete {{ addslashes($row->name) }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-500">None yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
