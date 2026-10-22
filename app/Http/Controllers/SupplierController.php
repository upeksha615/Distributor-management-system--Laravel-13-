<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::withCount('purchaseOrders')
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")))
            ->orderBy('name');

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'suppliers', 'Suppliers', [
                'Code' => 'text', 'Name' => 'text', 'Contact person' => 'text', 'Phone' => 'text', 'Email' => 'text',
                'Address' => 'text', 'Country' => 'text', 'Purchase orders' => 'number', 'Active' => 'text',
            ], $suppliers->get()->map(fn ($s) => [
                $s->code, $s->name, $s->contact_person, $s->phone, $s->email, $s->address, $s->country, $s->purchase_orders_count, $s->is_active ? 'Yes' : 'No',
            ]), $this->filterSummary($request));
        }

        $suppliers = $suppliers->paginate(25)->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function show(Supplier $supplier)
    {
        $orders = $supplier->purchaseOrders()->latest('id')->paginate(20);
        $balance = $supplier->balance();
        $openBills = $supplier->bills()->open()->orderBy('due_date')->orderBy('id')->get();

        return view('suppliers.show', compact('supplier', 'orders', 'balance', 'openBills'));
    }

    public function create()
    {
        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true, 'payment_terms_days' => 30])]);
    }

    public function store(Request $request)
    {
        $supplier = Supplier::create($this->validated($request));

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier created.');
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request, $supplier));

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier updated.');
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'code' => ['required', 'max:30', Rule::unique('suppliers')->ignore($supplier)],
            'name' => ['required', 'max:255'],
            'contact_person' => ['nullable', 'max:255'],
            'phone' => ['nullable', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'max:255'],
            'country' => ['nullable', 'max:100'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
