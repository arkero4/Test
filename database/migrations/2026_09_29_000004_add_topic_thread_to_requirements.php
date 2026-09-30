<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->string('topic_key')->nullable()->after('external_reference');
            $table->uuid('codex_thread_id')->nullable()->after('topic_key');
            $table->index(['project_id', 'topic_key']);
        });
    }

    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'topic_key']);
            $table->dropColumn(['topic_key', 'codex_thread_id']);
        });
    }
};
