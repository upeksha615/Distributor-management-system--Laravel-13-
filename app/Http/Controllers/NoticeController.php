<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Notice board and holiday calendar shown on the dashboard (permission notices.manage). */
class NoticeController extends Controller
{
    public function index()
    {
        return view('notices.index', [
            'notices' => Notice::with('user')->orderByDesc('starts_on')->orderByDesc('id')->paginate(25),
            'editing' => request('edit') ? Notice::find(request('edit')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $notice = Notice::create($this->validated($request) + ['user_id' => $request->user()->id]);
        AuditLog::record('notice.create', $notice, "Notice posted: {$notice->title}.");

        return redirect()->route('notices.index')->with('success', 'Notice posted.');
    }

    public function update(Request $request, Notice $notice)
    {
        $notice->update($this->validated($request));
        AuditLog::record('notice.update', $notice, "Notice updated: {$notice->title}.");

        return redirect()->route('notices.index')->with('success', 'Notice updated.');
    }

    public function destroy(Notice $notice)
    {
        AuditLog::record('notice.delete', $notice, "Notice removed: {$notice->title}.");
        $notice->delete();

        return back()->with('success', 'Notice removed.');
    }

    public function holidays(Request $request)
    {
        $year = (int) $request->input('year', today()->year);

        return view('notices.holidays', [
            'year' => $year,
            'holidays' => Holiday::whereYear('date', $year)->orderBy('date')->get(),
            'years' => Holiday::selectRaw('YEAR(date) as y')->distinct()->orderBy('y')->pluck('y')->push(today()->year)->push(today()->year + 1)->unique()->sort()->values(),
        ]);
    }

    public function storeHoliday(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'max:150', Rule::unique('holidays')->where('date', $request->input('date'))],
        ]);
        $holiday = Holiday::create($data + [
            'is_bank' => $request->boolean('is_bank'), 'is_public' => $request->boolean('is_public'), 'is_mercantile' => $request->boolean('is_mercantile'),
        ]);
        AuditLog::record('holiday.create', $holiday, "Holiday added: {$holiday->date->format('d/m/Y')} {$holiday->name}.");

        return redirect()->route('holidays.index', ['year' => $holiday->date->year])->with('success', 'Holiday added.');
    }

    public function destroyHoliday(Holiday $holiday)
    {
        AuditLog::record('holiday.delete', $holiday, "Holiday removed: {$holiday->date->format('d/m/Y')} {$holiday->name}.");
        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'max:150'],
            'body' => ['nullable', 'max:2000'],
            'level' => ['required', Rule::in(array_keys(Notice::LEVELS))],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
    }
}
