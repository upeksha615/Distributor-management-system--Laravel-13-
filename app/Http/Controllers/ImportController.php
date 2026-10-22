<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Services\ImportService;
use App\Support\Exporter;
use App\Support\SpreadsheetReader;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(private ImportService $imports) {}

    public function index(Request $request)
    {
        $types = collect(ImportService::types())->filter(fn ($t) => $request->user()->can($t['permission']));

        return view('imports.index', [
            'types' => $types,
            'warehouses' => Warehouse::orderBy('name')->pluck('name'),
        ]);
    }

    /** Excel template with the expected headings and one example row. */
    public function template(Request $request, string $type)
    {
        $definition = $this->definition($request, $type);
        $columns = collect($definition['columns'])->mapWithKeys(fn ($c, $key) => [$key => 'text'])->all();
        $example = collect($definition['columns'])->map(fn ($c) => $c[1])->values()->all();

        return Exporter::download($request->query('format') === 'csv' ? 'csv' : 'xlsx', $type.'-import-template', $definition['title'], $columns, [$example]);
    }

    public function store(Request $request, string $type)
    {
        $definition = $this->definition($request, $type);
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,csv,txt'],
            'mode' => ['nullable', 'in:set,add'],
            'reason' => ['nullable', 'max:255'],
        ], ['file.mimes' => 'Upload an Excel (.xlsx) or CSV file.']);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'xlsx';

        try {
            $rows = SpreadsheetReader::rows($file->getRealPath(), $extension);
        } catch (\Throwable $e) {
            return redirect()->route('imports.index')->with('error', 'Could not read the file. Save it as .xlsx or .csv and try again. ('.$e->getMessage().')');
        }

        $result = $this->imports->import($type, $rows, [
            'mode' => $request->input('mode', 'set'),
            'reason' => $request->input('reason') ?: 'Import: '.$file->getClientOriginalName(),
        ]);

        if ($result['errors']) {
            return redirect()->route('imports.index')->with([
                'import_type' => $type,
                'import_errors' => $result['errors'],
                'error' => "{$definition['title']} import failed: ".count($result['errors']).' row(s) have problems. Nothing was imported. Fix the rows below and upload again.',
            ]);
        }

        return redirect()->route('imports.index')->with('success', sprintf(
            '%s imported from %s: %d created, %d updated, %d unchanged.',
            $definition['title'], $file->getClientOriginalName(), $result['created'], $result['updated'], $result['unchanged'],
        ));
    }

    private function definition(Request $request, string $type): array
    {
        $definition = ImportService::types()[$type] ?? abort(404);
        abort_unless($request->user()->can($definition['permission']), 403, 'You do not have access to this import.');

        return $definition;
    }
}
