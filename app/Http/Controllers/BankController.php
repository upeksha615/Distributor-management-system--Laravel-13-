<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Support\SpreadsheetReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Bank & branch master (seeded from the CBSL list of licensed banks; codes and branches maintained here). */
class BankController extends Controller
{
    public function index(Request $request)
    {
        $banks = Bank::withCount('branches')
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('short_name', 'like', "%{$t}%")->orWhere('code', $t)))
            ->orderByDesc('is_active')->orderBy('type')->orderBy('name')
            ->get();

        return view('banks.index', compact('banks'));
    }

    public function store(Request $request)
    {
        $bank = Bank::create($this->validated($request));
        AuditLog::record('bank.create', $bank, "Added bank {$bank->name}.");

        return back()->with('success', "{$bank->name} added.");
    }

    public function update(Request $request, Bank $bank)
    {
        $bank->update($this->validated($request, $bank));

        return back()->with('success', "{$bank->name} updated.");
    }

    public function branches(Request $request, Bank $bank)
    {
        $branches = $bank->branches()
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('code', $t)->orWhere('city', 'like', "%{$t}%")))
            ->orderBy('name')->paginate(50)->withQueryString();

        return view('banks.branches', compact('bank', 'branches'));
    }

    public function storeBranch(Request $request, Bank $bank)
    {
        $bank->branches()->create($this->validatedBranch($request, $bank));

        return back()->with('success', 'Branch added.');
    }

    public function updateBranch(Request $request, Bank $bank, BankBranch $branch)
    {
        abort_unless($branch->bank_id === $bank->id, 404);
        $branch->update($this->validatedBranch($request, $bank, $branch));

        return back()->with('success', 'Branch updated.');
    }

    /** Import branches from a CSV / Excel file with columns: name, code, city. Existing names are updated. */
    public function importBranches(Request $request, Bank $bank)
    {
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt']]);
        $file = $request->file('file');
        $rows = SpreadsheetReader::rows($file->getRealPath(), strtolower($file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'xlsx');

        $created = $updated = 0;
        $errors = [];
        DB::transaction(function () use ($rows, $bank, &$created, &$updated, &$errors) {
            foreach ($rows as $line => $row) {
                $name = trim($row['name'] ?? $row['branch'] ?? $row['branch_name'] ?? '');
                if ($name === '') {
                    $errors[] = "Row {$line}: branch name is empty.";

                    continue;
                }
                $branch = $bank->branches()->firstOrNew(['name' => $name]);
                $branch->fill(['code' => ($row['code'] ?? $row['branch_code'] ?? '') ?: $branch->code, 'city' => ($row['city'] ?? '') ?: $branch->city, 'is_active' => true]);
                $branch->exists ? $updated++ : $created++;
                $branch->save();
            }
        });
        AuditLog::record('bank.branches_import', $bank, "Imported branches for {$bank->name}: {$created} new, {$updated} updated.");

        return back()->with($errors ? 'error' : 'success', "Branches imported: {$created} new, {$updated} updated.".($errors ? ' Skipped: '.implode(' ', array_slice($errors, 0, 5)) : ''));
    }

    /** JSON for searchable branch pickers (cheque entry, bank accounts). */
    public function branchLookup(Request $request, Bank $bank)
    {
        return $bank->branches()->where('is_active', true)
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('code', $t)))
            ->orderBy('name')->limit(30)->get(['id', 'name', 'code', 'city']);
    }

    private function validated(Request $request, ?Bank $bank = null): array
    {
        return $request->validate([
            'name' => ['required', 'max:255', Rule::unique('banks')->ignore($bank)],
            'short_name' => ['nullable', 'max:30'],
            'code' => ['nullable', 'max:10', Rule::unique('banks')->ignore($bank)],
            'type' => ['required', Rule::in(array_keys(Bank::TYPES))],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }

    private function validatedBranch(Request $request, Bank $bank, ?BankBranch $branch = null): array
    {
        return $request->validate([
            'name' => ['required', 'max:255', Rule::unique('bank_branches')->where('bank_id', $bank->id)->ignore($branch)],
            'code' => ['nullable', 'max:10'],
            'city' => ['nullable', 'max:100'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
