<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\RepVisit;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Admin / manager view of sales reps in the field. */
class FieldController extends Controller
{
    /** Distance from the dealer's saved location above which a check-in is flagged. */
    public const FAR_METRES = 500;

    public function index(Request $request)
    {
        $date = Carbon::parse($request->input('date', today()->toDateString()));

        $log = RepVisit::with(['rep', 'customer', 'orders', 'payments'])
            ->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->outcome, fn ($q, $o) => $q->where('outcome', $o))
            ->when($request->q, fn ($q, $t) => $q->whereHas('customer', fn ($c) => $c->search($t)))
            ->whereDate('checked_in_at', '>=', $request->input('from', $date->toDateString()))
            ->whereDate('checked_in_at', '<=', $request->input('to', $date->toDateString()))
            ->latest('checked_in_at');

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'rep-visits', 'Rep visits', [
                'Rep' => 'text', 'Dealer' => 'text', 'City' => 'text', 'Check in' => 'text', 'Check out' => 'text', 'Minutes' => 'number',
                'Distance (m)' => 'number', 'Outcome' => 'text', 'Orders' => 'money', 'Collected' => 'money', 'Notes' => 'text', 'Next visit' => 'text',
            ], $log->get()->map(fn ($v) => [
                $v->rep->name, $v->customer->name, $v->customer->city, $v->checked_in_at->format('d/m/Y H:i'), $v->checked_out_at?->format('H:i'),
                $v->minutes(), $v->distance_m, $v->outcomeLabel(), $v->orders->where('status', '!=', 'cancelled')->sum('total'),
                $v->payments->whereNotIn('status', ['rejected', 'reversed'])->sum('amount'), $v->notes, $v->next_visit_date?->format('d/m/Y'),
            ]), $this->filterSummary($request));
        }

        $reps = User::where('is_active', true)->whereIn('role', ['sales', 'manager', 'admin'])->orderBy('name')->get();

        // Per-rep totals for the selected day.
        $dayVisits = RepVisit::with(['orders', 'payments'])->whereDate('checked_in_at', $date)->get()->groupBy('user_id');
        $repSummary = $reps->map(fn ($rep) => [
            'rep' => $rep,
            'visits' => ($dayVisits[$rep->id] ?? collect())->count(),
            'orders' => ($dayVisits[$rep->id] ?? collect())->flatMap->orders->where('status', '!=', 'cancelled')->count(),
            'order_value' => ($dayVisits[$rep->id] ?? collect())->flatMap->orders->where('status', '!=', 'cancelled')->sum('total'),
            'collected' => ($dayVisits[$rep->id] ?? collect())->flatMap->payments->whereNotIn('status', ['rejected', 'reversed'])->sum('amount'),
            'first' => ($dayVisits[$rep->id] ?? collect())->min('checked_in_at'),
            'last' => ($dayVisits[$rep->id] ?? collect())->max('checked_in_at'),
        ])->filter(fn ($r) => $r['visits'] > 0 || $r['rep']->role === 'sales')->values();

        return view('field.index', [
            'date' => $date,
            'active' => RepVisit::with(['rep', 'customer'])->active()->latest('checked_in_at')->get(),
            'repSummary' => $repSummary,
            'pendingOrders' => SalesOrder::with(['customer', 'user'])->where('source', 'rep')->where('status', 'pending')->latest('id')->get(),
            'log' => $log->paginate(40)->withQueryString(),
            'reps' => $reps,
        ]);
    }

    /** JSON feed of rep events since a timestamp, polled by admin pages for live alerts. */
    public function live(Request $request)
    {
        // Timestamps are UTC; compare in the app timezone the database values are stored in.
        $since = Carbon::createFromTimestamp((int) $request->input('since', now()->subMinutes(5)->timestamp), config('app.timezone'));
        $events = collect();

        foreach (RepVisit::with(['rep', 'customer'])->where('checked_in_at', '>', $since)->get() as $v) {
            $far = $v->distance_m !== null && $v->distance_m > self::FAR_METRES;
            $events->push(['at' => $v->checked_in_at->timestamp, 'type' => 'checkin', 'url' => route('rep.visits.show', $v),
                'text' => "{$v->rep->name} checked in at {$v->customer->name}".($far ? " ({$v->distance_m} m from the dealer's location!)" : '')]);
        }
        foreach (RepVisit::with(['rep', 'customer'])->where('checked_out_at', '>', $since)->get() as $v) {
            $events->push(['at' => $v->checked_out_at->timestamp, 'type' => 'checkout', 'url' => route('rep.visits.show', $v),
                'text' => "{$v->rep->name} left {$v->customer->name} after {$v->minutes()} min: {$v->outcomeLabel()}"]);
        }
        foreach (SalesOrder::with(['user', 'customer'])->where('source', 'rep')->where('created_at', '>', $since)->get() as $o) {
            $events->push(['at' => $o->created_at->timestamp, 'type' => 'order', 'url' => route('sales-orders.show', $o),
                'text' => "{$o->user?->name} placed order {$o->number} for {$o->customer->name}: ".money($o->total)]);
        }
        foreach (Payment::with(['user', 'customer'])->whereNotNull('rep_visit_id')->where('created_at', '>', $since)->get() as $p) {
            $events->push(['at' => $p->created_at->timestamp, 'type' => 'payment', 'url' => route('payments.show', $p),
                'text' => "{$p->user?->name} collected ".money($p->amount)." ({$p->methodLabel()}) from {$p->customer->name}"]);
        }

        return [
            'now' => now()->timestamp,
            'active' => RepVisit::active()->count(),
            'events' => $events->sortBy('at')->values(),
        ];
    }
}
