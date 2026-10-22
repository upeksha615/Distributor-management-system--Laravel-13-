<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\Exporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class Controller
{
    /** The requested export format (?export=xlsx|csv|pdf), or null for a normal page. Needs reports.export. */
    protected function exportFormat(Request $request): ?string
    {
        if (! in_array($request->query('export'), Exporter::FORMATS, true)) {
            return null;
        }
        abort_unless($request->user()?->can('reports.export'), 403, 'You are not allowed to export data.');

        return $request->query('export');
    }

    /** Limit a query of dealer documents (invoices, orders, payments...) to dealers the user may see. */
    protected function dealerScope(Builder $query, string $relation = 'customer'): Builder
    {
        $user = request()->user();

        return $user->canSeeAllDealers() ? $query : $query->whereHas($relation, fn ($c) => $c->where('sales_rep_id', $user->id));
    }

    /** 403 unless the user may see this dealer (sales reps see only their assigned dealers). */
    protected function authorizeDealer(?Customer $customer): void
    {
        abort_unless($customer && $customer->isVisibleTo(request()->user()), 403, 'This dealer is not assigned to you.');
    }

    /** "Filtered: from 01/10/2026 to 14/10/2026" style subtitle for exported files. */
    protected function filterSummary(Request $request): ?string
    {
        $parts = collect($request->except(['export', 'page']))
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v, $k) => str_replace('_', ' ', $k).': '.(is_array($v) ? implode(',', $v) : $v));

        return $parts->isEmpty() ? null : 'Filter - '.$parts->join(', ');
    }
}
