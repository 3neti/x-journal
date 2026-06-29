<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_journal_reference_counters', function (Blueprint $table): void {
            $table->id();
            $table->string('prefix');
            $table->string('year', 4);
            $table->unsignedBigInteger('next_sequence')->default(1);
            $table->timestamps();

            $table->unique(['prefix', 'year']);
        });

        Schema::table('execution_journal_entries', function (Blueprint $table): void {
            $table->string('actor_type')->nullable()->index()->after('occurred_at');
            $table->string('actor_id')->nullable()->index()->after('actor_type');
            $table->string('subject_type')->nullable()->index()->after('actor_id');
            $table->string('subject_id')->nullable()->index()->after('subject_type');
            $table->string('correlation_id')->nullable()->index()->after('subject_id');
            $table->string('causation_id')->nullable()->index()->after('correlation_id');
            $table->string('execution_id')->nullable()->index()->after('causation_id');
        });
    }

    public function down(): void
    {
        Schema::table('execution_journal_entries', function (Blueprint $table): void {
            $table->dropColumn([
                'actor_type',
                'actor_id',
                'subject_type',
                'subject_id',
                'correlation_id',
                'causation_id',
                'execution_id',
            ]);
        });

        Schema::dropIfExists('execution_journal_reference_counters');
    }
};
