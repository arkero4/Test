<?php

use App\Models\Worker;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    Worker::whereIn('status', ['ONLINE', 'BUSY'])
        ->where('last_heartbeat_at', '<', now()->subSeconds(config('orchestrator.heartbeat_timeout_seconds')))
        ->update(['status' => 'OFFLINE']);
})->everyMinute()->name('workers:mark-stale')->withoutOverlapping();
