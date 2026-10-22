<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Exporter;
use App\Support\Settings;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('settings.edit', ['values' => Settings::current(), 'fields' => Settings::FIELDS]);
    }

    public function update(Request $request)
    {
        $rules = collect(Settings::FIELDS)->map(fn ($f) => str_starts_with($f[2], 'select:') ? ['required', \Illuminate\Validation\Rule::in(array_keys(Settings::options($f[2])))] : match ($f[2]) {
            'number' => ['required', 'numeric', 'min:0', 'max:100'],
            'bool' => ['boolean'],
            'money' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'textarea' => ['nullable', 'max:1000'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            default => ['nullable', 'max:255'],
        })->all();
        $rules['company_name'] = ['required', 'max:255'];
        $rules['currency'] = ['required', 'max:10'];

        $data = $request->validate($rules);
        foreach (Settings::FIELDS as $key => $f) {
            if ($f[2] === 'bool') {
                $data[$key] = $request->boolean($key) ? '1' : '0';
            }
        }

        Settings::save(array_filter($data, fn ($v, $k) => $v !== null || Settings::FIELDS[$k][2] !== 'days', ARRAY_FILTER_USE_BOTH));
        AuditLog::record('settings.update', null, 'Business settings updated.');

        return back()->with('success', 'Settings saved.');
    }

    public function audit(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->action, fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->when($request->q, fn ($q, $t) => $q->where('description', 'like', "%{$t}%"))
            ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id');

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'audit-log', 'Audit log', [
                'Time' => 'text', 'User' => 'text', 'Action' => 'text', 'Details' => 'text', 'IP' => 'text',
            ], $logs->limit(20000)->get()->map(fn ($l) => [
                $l->created_at->format('Y-m-d H:i'), $l->user?->name, $l->action, $l->description, $l->ip,
            ]), $this->filterSummary($request));
        }

        return view('settings.audit', [
            'logs' => $logs->paginate(50)->withQueryString(),
            'users' => User::orderBy('name')->get(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
