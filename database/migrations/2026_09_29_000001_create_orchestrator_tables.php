<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('capabilities')->nullable();
            $table->string('status')->default('OFFLINE');
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('agent_version')->nullable();
            $table->json('environment')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('client')->nullable();
            $table->text('description')->nullable();
            $table->string('stack')->nullable();
            $table->string('version')->nullable();
            $table->string('type')->default('other');
            $table->string('repository')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->foreignId('preferred_worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->string('preferred_agent')->nullable();
            $table->text('instructions')->nullable();
            $table->json('test_commands')->nullable();
            $table->text('restrictions')->nullable();
            $table->text('documentation')->nullable();
            $table->timestamps();
        });

        Schema::create('project_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('title');
            $table->longText('content');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['project_id', 'kind']);
        });

        Schema::create('worker_project', function (Blueprint $table) {
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->primary(['worker_id', 'project_id']);
        });

        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->string('kind')->default('DEVELOPMENT');
            $table->string('source');
            $table->string('external_reference')->nullable();
            $table->string('sender')->nullable();
            $table->string('subject');
            $table->longText('original_content');
            $table->text('summary')->nullable();
            $table->longText('context')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('priority')->default('NORMAL');
            $table->string('risk')->default('UNKNOWN');
            $table->string('status')->default('RECEIVED');
            $table->decimal('classification_confidence', 4, 3)->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->timestamp('received_at')->useCurrent();
            $table->json('technical_analysis')->nullable();
            $table->timestamps();
            $table->unique(['source', 'external_reference']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->text('summary');
            $table->string('status')->default('DRAFT');
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('project_id')->constrained();
            $table->string('type')->default('implementation');
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('instructions')->nullable();
            $table->string('required_agent')->default('codex_local');
            $table->foreignId('required_worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->string('priority')->default('NORMAL');
            $table->string('status')->default('DRAFT');
            $table->boolean('parallel_allowed')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->timestamps();
            $table->index(['status', 'priority']);
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->primary(['task_id', 'depends_on_task_id']);
        });

        Schema::create('executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained();
            $table->string('agent');
            $table->string('status')->default('RUNNING');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->longText('prompt')->nullable();
            $table->text('summary')->nullable();
            $table->longText('stdout')->nullable();
            $table->longText('stderr')->nullable();
            $table->json('modified_files')->nullable();
            $table->string('branch')->nullable();
            $table->string('commit')->nullable();
            $table->json('tests')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->decimal('cost', 12, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained()->cascadeOnDelete();
            $table->string('level')->default('info');
            $table->text('message');
            $table->timestamps();
        });

        Schema::create('requirement_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('execution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor');
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['requirement_id', 'created_at']);
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('status')->default('PENDING');
            $table->text('reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained()->cascadeOnDelete();
            $table->string('agent');
            $table->string('status');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('usage')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['agent_runs', 'approvals', 'requirement_events', 'execution_logs', 'executions', 'task_dependencies', 'tasks', 'plans', 'requirements', 'worker_project', 'project_contexts', 'projects', 'workers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
