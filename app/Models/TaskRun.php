<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TaskRun extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];

    /**
     * Run a task and record the outcome. The callback returns a short result text.
     * Failures are recorded and then re-thrown so the command exits with an error.
     */
    public static function track(string $task, callable $callback): string
    {
        $run = static::create(['task' => $task, 'status' => 'running', 'started_at' => now()]);

        try {
            $output = (string) $callback();
            $run->update(['status' => 'success', 'output' => Str::limit($output, 2000), 'finished_at' => now()]);

            return $output;
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'output' => Str::limit($e->getMessage(), 2000), 'finished_at' => now()]);

            throw $e;
        }
    }

    public static function latestFor(string $task): ?self
    {
        return static::where('task', $task)->latest('started_at')->latest('id')->first();
    }
}
