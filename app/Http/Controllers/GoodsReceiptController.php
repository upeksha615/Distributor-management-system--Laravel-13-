<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\PurchaseService;
use App\Support\Exporter;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request)
    {
        $receipts = GoodsReceipt::with(['purchaseOrder.supplier', 'warehouse'])
            ->when($request->q, fn ($q, $term) => $q->where('number', 'like', "%{$term}%")
                ->orWhere('supplier_invoice_no', 'like', "%{$term}%"))
            ->latest('id');

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'goods-received', 'Goods received notes', [
                'GRN' => 'text', 'Date' => 'text', 'PO' => 'text', 'Supplier' => 'text', 'Supplier invoice' => 'text', 'Warehouse' => 'text', 'Value' => 'money',
            ], $rows = $receipts->get()->map(fn ($g) => [
                $g->number, $g->received_date->format('d/m/Y'), $g->purchaseOrder->number, $g->purchaseOrder->supplier->name,
                $g->supplier_invoice_no, $g->warehouse->name, $g->total,
            ]), $this->filterSummary($request), ['Total', null, null, null, null, null, $rows->sum(fn ($r) => $r[6])]);
        }

        $receipts = $receipts->paginate(25)->withQueryString();

        return view('goods-receipts.index', compact('receipts'));
    }

    public function create(PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->canReceive(), 403, 'This purchase order is not open for receiving.');
        $purchaseOrder->load(['supplier', 'warehouse', 'items.product']);

        return view('goods-receipts.create', ['po' => $purchaseOrder]);
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $request->validate([
            'received_date' => ['required', 'date'],
            'supplier_invoice_no' => ['nullable', 'max:50'],
            'notes' => ['nullable', 'max:2000'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $grn = $this->purchases->receive($purchaseOrder, $data['quantities'], $data);

        return redirect()->route('goods-receipts.show', $grn)->with('success', "Goods received. {$grn->number} created and stock updated.");
    }

    public function show(GoodsReceipt $goodsReceipt)
    {
        $goodsReceipt->load(['purchaseOrder.supplier', 'warehouse', 'user', 'items.product']);

        return view('goods-receipts.show', ['grn' => $goodsReceipt]);
    }
}
