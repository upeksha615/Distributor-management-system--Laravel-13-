<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\RepVisit;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\PaymentService;
use App\Services\SalesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** The sales rep's field app: check in at dealers, take orders, collect payments, check out. */
class RepController extends Controller
{
    public function __construct(private SalesService $sales, private PaymentService $payments) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $seeAll = $user->canSeeAllDealers() || $request->boolean('all');

        $dealers = Customer::where('is_active', true)
            ->where('is_walk_in', false)
            ->when(! $seeAll, fn ($q) => $q->where('sales_rep_id', $user->id))
            ->search($request->q)
            ->withSum(['invoices as balance' => fn ($q) => $q->where('status', '!=', 'cancelled')], DB::raw('total - paid_amount - credited_amount'))
            ->withMax('visits as last_visit', 'checked_in_at')
            ->orderBy('name')
            ->limit(100)
            ->get();

        $todayVisits = RepVisit::with('customer')->where('user_id', $user->id)->whereDate('checked_in_at', today())->latest('checked_in_at')->get();

        return view('rep.index', [
            'active' => RepVisit::activeFor($user)?->load('customer'),
            'dealers' => $dealers,
            'seeAll' => $seeAll,
            'todayVisits' => $todayVisits,
            'today' => [
                'visits' => $todayVisits->count(),
                'orders' => SalesOrder::where('source', 'rep')->where('user_id', $user->id)->whereDate('created_at', today())->where('status', '!=', 'cancelled')->count(),
                'order_value' => (float) SalesOrder::where('source', 'rep')->where('user_id', $user->id)->whereDate('created_at', today())->where('status', '!=', 'cancelled')->sum('total'),
                'collected' => (float) Payment::whereNotNull('rep_visit_id')->where('user_id', $user->id)->whereDate('created_at', today())->whereNotIn('status', ['rejected', 'reversed'])->sum('amount'),
            ],
            'dueVisits' => RepVisit::with('customer')->where('user_id', $user->id)->whereNotNull('next_visit_date')
                ->where('next_visit_date', '<=', today())->whereNotNull('checked_out_at')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('rep_visits as later')
                    ->whereColumn('later.customer_id', 'rep_visits.customer_id')->whereColumn('later.checked_in_at', '>', 'rep_visits.checked_in_at'))
                ->orderBy('next_visit_date')->limit(10)->get(),
        ]);
    }

    public function checkIn(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);
        $user = $request->user();

        if ($active = RepVisit::activeFor($user)) {
            return redirect()->route('rep.visits.show', $active)->with('error', "You are still checked in at {$active->customer->name}. Check out first.");
        }

        $customer = Customer::findOrFail($data['customer_id']);
        $distance = null;
        $hasGps = isset($data['lat'], $data['lng']);

        if ($hasGps && $customer->latitude !== null) {
            $distance = RepVisit::distance((float) $customer->latitude, (float) $customer->longitude, (float) $data['lat'], (float) $data['lng']);
        } elseif ($hasGps) {
            // First visit with GPS: remember this as the dealer's location for future checks.
            $customer->update(['latitude' => $data['lat'], 'longitude' => $data['lng']]);
            $distance = 0;
        }

        $visit = RepVisit::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'checked_in_at' => now(),
            'check_in_lat' => $data['lat'] ?? null,
            'check_in_lng' => $data['lng'] ?? null,
            'check_in_accuracy' => isset($data['accuracy']) ? (int) $data['accuracy'] : null,
            'distance_m' => $distance,
        ]);

        return redirect()->route('rep.visits.show', $visit)->with('success', "Checked in at {$customer->name}.".($hasGps ? '' : ' Location was not available.'));
    }

    public function show(Request $request, RepVisit $visit)
    {
        $this->authorizeVisit($request, $visit);
        $visit->load(['customer.salesRep', 'rep', 'orders', 'payments']);
        $customer = $visit->customer;

        $topProducts = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->join('products as p', 'p.id', '=', 'ii.product_id')
            ->where('i.customer_id', $customer->id)
            ->where('i.status', '!=', 'cancelled')
            ->where('i.invoice_date', '>=', today()->subMonths(6))
            ->groupBy('p.id', 'p.sku', 'p.name')
            ->selectRaw('p.id, p.sku, p.name, SUM(ii.quantity) as qty, MAX(i.invoice_date) as last_date')
            ->orderByDesc('qty')->limit(10)->get();

        return view('rep.visit', [
            'visit' => $visit,
            'customer' => $customer,
            'balance' => $customer->outstandingBalance(),
            'openInvoices' => $customer->invoices()->open()->orderBy('due_date')->get(),
            'recentOrders' => $customer->salesOrders()->latest('id')->limit(5)->get(),
            'topProducts' => $topProducts,
            'banks' => \App\Models\Bank::where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_name']),
            'previousVisits' => $customer->visits()->with('rep')->where('id', '!=', $visit->id)->latest('checked_in_at')->limit(5)->get(),
        ]);
    }

    public function orderForm(Request $request, RepVisit $visit)
    {
        $this->authorizeVisit($request, $visit, true);
        $customer = $visit->customer;

        return view('rep.order', [
            'visit' => $visit,
            'customer' => $customer,
            'customers' => collect([$customer]), // lets the line editor price lines at this dealer's level
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'warehouseId' => Warehouse::where('is_active', true)->orderBy('id')->value('id'),
            'taxPercent' => config('distributor.tax_rate'),
        ]);
    }

    public function orderStore(Request $request, RepVisit $visit)
    {
        $this->authorizeVisit($request, $visit, true);
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], ['items.required' => 'Add at least one product.']);

        $order = $this->sales->saveOrder($data + [
            'customer_id' => $visit->customer_id,
            'order_date' => today()->toDateString(),
            'source' => 'rep',
            'rep_visit_id' => $visit->id,
        ]);

        return redirect()->route('rep.visits.show', $visit)->with('success', "Order {$order->number} sent to the office (".money($order->total).').');
    }

    public function paymentStore(Request $request, RepVisit $visit)
    {
        $this->authorizeVisit($request, $visit, true);
        $data = $request->validate([
            'method' => ['required', Rule::in(['cash', 'cheque', 'bank_transfer'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'max:100'],
            'cheque_number' => ['required_if:method,cheque', 'nullable', 'max:50'],
            'bank_id' => ['required_if:method,cheque', 'nullable', 'exists:banks,id'],
            'cheque_date' => ['required_if:method,cheque', 'nullable', 'date'],
            'notes' => ['nullable', 'max:500'],
        ]);

        $payment = $this->payments->record($visit->customer, $data + [
            'payment_date' => today()->toDateString(),
            'rep_visit_id' => $visit->id,
            'channel' => 'rep',
            'notes' => trim(($data['notes'] ?? '').' Collected by '.$request->user()->name.' on visit.'),
        ]);

        return redirect()->route('rep.visits.show', $visit)->with('success', "Payment {$payment->number} recorded: ".money($payment->amount).' ('.$payment->methodLabel().'). Hand it to the office; it counts once the accountant verifies it.');
    }

    public function checkOut(Request $request, RepVisit $visit)
    {
        $this->authorizeVisit($request, $visit, true);
        $data = $request->validate([
            'outcome' => ['required', Rule::in(array_keys(RepVisit::OUTCOMES))],
            'notes' => ['nullable', 'max:2000'],
            'next_visit_date' => ['nullable', 'date', 'after_or_equal:today'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        $visit->update([
            'checked_out_at' => now(),
            'outcome' => $data['outcome'],
            'notes' => $data['notes'] ?? null,
            'next_visit_date' => $data['next_visit_date'] ?? null,
            'check_out_lat' => $data['lat'] ?? null,
            'check_out_lng' => $data['lng'] ?? null,
        ]);

        return redirect()->route('rep.index')->with('success', "Checked out from {$visit->customer->name} after {$visit->minutes()} min.");
    }

    /** The rep's own visit history. */
    public function history(Request $request)
    {
        $visits = RepVisit::with(['customer', 'orders', 'payments'])
            ->where('user_id', $request->user()->id)
            ->when($request->from, fn ($q, $d) => $q->whereDate('checked_in_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('checked_in_at', '<=', $d))
            ->latest('checked_in_at')
            ->paginate(30)->withQueryString();

        return view('rep.history', compact('visits'));
    }

    /** Admin may update a dealer's map location (e.g. after a wrong first check-in). */
    public function resetLocation(Request $request, Customer $customer)
    {
        abort_unless($request->user()->can('field.monitor'), 403);
        $customer->update(['latitude' => null, 'longitude' => null]);
        AuditLog::record('customer.location', $customer, "Dealer location cleared for {$customer->name}; next check-in will set it.");

        return back()->with('success', 'Dealer location cleared. The next check-in will save the new location.');
    }

    private function authorizeVisit(Request $request, RepVisit $visit, bool $mustBeActive = false): void
    {
        abort_unless($visit->user_id === $request->user()->id || $request->user()->can('field.monitor'), 403);
        if ($mustBeActive) {
            abort_unless($visit->isActive() && $visit->user_id === $request->user()->id, 403, 'This visit is checked out. Check in again to take orders or payments.');
        }
    }
}
