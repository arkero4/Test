<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ExecutionController;
use App\Http\Controllers\Web\IntegrationController;
use App\Http\Controllers\Web\ProjectController;
use App\Http\Controllers\Web\RequirementController;
use App\Http\Controllers\Web\TaskController;
use App\Http\Controllers\Web\WorkerController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'form'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::resource('projects', ProjectController::class)->except('show');
    Route::post('/projects/{project}/contexts', [ProjectController::class, 'context'])->name('projects.contexts.store');
    Route::delete('/projects/{project}/contexts/{context}', [ProjectController::class, 'deleteContext'])->name('projects.contexts.destroy');
    Route::resource('workers', WorkerController::class)->except('show');
    Route::get('/integrations', IntegrationController::class)->name('integrations.index');
    Route::post('/workers/{worker}/token', [WorkerController::class, 'token'])->name('workers.token');
    Route::resource('requirements', RequirementController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('/requirements/{requirement}/transition', [RequirementController::class, 'transition'])->name('requirements.transition');
    Route::post('/requirements/{requirement}/response', [RequirementController::class, 'recordResponse'])->name('requirements.response');
    Route::post('/requirements/{requirement}/plans', [RequirementController::class, 'plan'])->name('requirements.plans.store');
    Route::post('/requirements/{requirement}/approvals', [RequirementController::class, 'requestApproval'])->name('requirements.approvals.store');
    Route::post('/requirements/{requirement}/approvals/{approval}/decide', [RequirementController::class, 'decide'])->name('requirements.approvals.decide');
    Route::get('/requirements/{requirement}/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::post('/requirements/{requirement}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::post('/tasks/{task}/queue', [TaskController::class, 'queue'])->name('tasks.queue');
    Route::post('/tasks/{task}/retry', [TaskController::class, 'retry'])->name('tasks.retry');
    Route::get('/executions/{execution}', [ExecutionController::class, 'show'])->name('executions.show');
});
