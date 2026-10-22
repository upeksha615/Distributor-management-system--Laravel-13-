<?php

use App\Support\ScheduledTasks;
use Illuminate\Support\Facades\Schedule;

// Automatic tasks (summary email, reminders, housekeeping). See app/Support/ScheduledTasks.php.
ScheduledTasks::register(Schedule::getFacadeRoot());
