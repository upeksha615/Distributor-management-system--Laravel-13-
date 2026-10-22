<x-layouts.app title="Import data">
    <x-page-header title="Import data" subtitle="Upload Excel (.xlsx) or CSV files. All rows are checked first; if any row has a problem, nothing is imported." />

    @if (session('import_errors'))
        <div class="card mb-6 border-red-300">
            <h2 class="border-b border-red-200 bg-red-50 px-5 py-3 font-semibold text-red-800">
                Problems in the {{ \App\Services\ImportService::types()[session('import_type')]['title'] ?? '' }} file
            </h2>
            <div class="max-h-96 overflow-y-auto">
                <table class="table">
                    <thead><tr><th class="w-24">Row</th><th>Problem</th></tr></thead>
                    <tbody>
                    @foreach (session('import_errors') as $line => $message)
                        <tr><td>{{ $line ?: '-' }}</td><td class="text-red-700">{{ $message }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($types as $key => $type)
            <div class="card" x-data="{ showCols: false }">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h2 class="font-semibold">{{ $type['title'] }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $type['help'] }}</p>
                </div>
                <div class="space-y-4 p-5">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-slate-600">1. Download the template:</span>
                        <a href="{{ route('imports.template', $key) }}" class="btn btn-secondary btn-sm">Excel template</a>
                        <a href="{{ route('imports.template', [$key, 'format' => 'csv']) }}" class="btn btn-secondary btn-sm">CSV template</a>
                        <button type="button" @click="showCols = !showCols" class="link text-xs" x-text="showCols ? 'Hide columns' : 'Show columns'"></button>
                    </div>

                    <table x-show="showCols" x-cloak class="table text-xs">
                        <thead><tr><th>Column</th><th>Example</th><th>Notes</th></tr></thead>
                        <tbody>
                        @foreach ($type['columns'] as $column => [$rules, $example, $note])
                            <tr>
                                <td class="font-mono">{{ $column }}@if (in_array('required', $rules))<span class="text-red-500">*</span>@endif</td>
                                <td>{{ $example }}</td>
                                <td class="text-slate-500">{{ $note }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                    <form method="POST" action="{{ route('imports.store', $key) }}" enctype="multipart/form-data" class="space-y-3"
                          onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Importing...'">
                        @csrf
                        <div>
                            <label class="label">2. Upload the completed file</label>
                            <input type="file" name="file" accept=".xlsx,.csv" required
                                   class="block w-full text-sm file:mr-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:px-3 file:py-1.5 file:text-sm">
                        </div>
                        @if ($key === 'stock')
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="label">Quantity column means</label>
                                    <select name="mode" class="input">
                                        <option value="set">Counted quantity (set stock to this)</option>
                                        <option value="add">Change (+ add / - remove)</option>
                                    </select>
                                </div>
                                <x-field label="Reason" name="reason" placeholder="e.g. Opening stock 2026" />
                            </div>
                            <p class="text-xs text-slate-500">Warehouses: {{ $warehouses->join(', ') }}</p>
                        @endif
                        <button class="btn btn-primary">Import {{ strtolower($type['title']) }}</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
