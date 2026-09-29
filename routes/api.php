<?php

use App\Http\Controllers\Api\RequirementIngestionController;
use App\Http\Controllers\Api\WorkerApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('worker.auth')->group(function () {
    Route::post('/workers/register', [WorkerApiController::class, 'register']);
    Route::post('/workers/heartbeat', [WorkerApiController::class, 'heartbeat']);
    Route::get('/workers/jobs/next', [WorkerApiController::class, 'next']);
    Route::post('/jobs/{task}/accept', [WorkerApiController::class, 'accept']);
    Route::post('/jobs/{execution}/progress', [WorkerApiController::class, 'progress']);
    Route::post('/jobs/{execution}/logs', [WorkerApiController::class, 'logs']);
    Route::post('/jobs/{execution}/complete', [WorkerApiController::class, 'complete']);
    Route::post('/jobs/{execution}/fail', [WorkerApiController::class, 'fail']);
    Route::post('/jobs/{execution}/approval', [WorkerApiController::class, 'requestApproval']);
});

Route::post('/v1/requirements', [RequirementIngestionController::class, 'store'])
    ->middleware(['ingestion.auth', 'throttle:60,1']);

Route::post('/mcp', \App\Http\Controllers\Api\WorkMcpController::class)
    ->middleware(['ingestion.auth', 'throttle:60,1']);
