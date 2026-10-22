<?php

namespace App\Console\Commands;

use App\Models\RepVisit;
use App\Models\TaskRun;
use Illuminate\Console\Command;

class CloseStaleVisits extends Command
{
    protected $signature = 'visits:close-stale';

    protected $description = 'Check out rep visits still open from a previous day (rep forgot to check out)';

    public function handle(): int
    {
        $result = TaskRun::track('visits:close-stale', function () {
            $visits = RepVisit::whereNull('checked_out_at')->where('checked_in_at', '<', today())->get();
            foreach ($visits as $visit) {
                $visit->update([
                    'checked_out_at' => $visit->checked_in_at->copy()->endOfDay(),
                    'notes' => trim($visit->notes."\nAuto-closed: the rep did not check out."),
                ]);
            }

            return $visits->count().' visit(s) closed.';
        });
        $this->info($result);

        return self::SUCCESS;
    }
}
