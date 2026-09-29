<?php

return [
    'max_active_executions' => (int) env('ORCHESTRATOR_MAX_ACTIVE', 4),
    'heartbeat_timeout_seconds' => (int) env('ORCHESTRATOR_HEARTBEAT_TIMEOUT', 120),
    'max_log_bytes' => 65536,
];
