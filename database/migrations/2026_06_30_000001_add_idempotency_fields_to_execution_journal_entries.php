<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('execution_journal_entries', function (Blueprint $table): void {
            $table->string('idempotency_key')->nullable()->unique()->after('metadata');
            $table->string('idempotency_fingerprint')->nullable()->after('idempotency_key');
            $table->index(['idempotency_key', 'idempotency_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('execution_journal_entries', function (Blueprint $table): void {
            $table->dropColumn([
                'idempotency_key',
                'idempotency_fingerprint',
            ]);
        });
    }
};
