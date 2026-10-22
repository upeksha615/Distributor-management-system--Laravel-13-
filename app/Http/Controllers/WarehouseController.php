<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::withSum('stockLevels as total_qty', 'quantity')->orderBy('name')->get();

        return view('warehouses.index', compact('warehouses'));
    }

    public function store(Request $request)
    {
        Warehouse::create($this->validated($request));

        return back()->with('success', 'Warehouse added.');
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $warehouse->update($this->validated($request, $warehouse));

        return back()->with('success', 'Warehouse updated.');
    }

    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        return $request->validate([
            'name' => ['required', 'max:255', Rule::unique('warehouses')->ignore($warehouse)],
            'location' => ['nullable', 'max:255'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
