<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('execution_journal_entries', function (Blueprint $table): void {
            $table->string('source_system')->nullable()->after('event_type');
            $table->string('source_event_id')->nullable()->after('source_system');
            $table->unique(['source_system', 'source_event_id'], 'execution_journal_source_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('execution_journal_entries', function (Blueprint $table): void {
            $table->dropUnique('execution_journal_source_identity_unique');
            $table->dropColumn(['source_system', 'source_event_id']);
        });
    }
};
