<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_statement_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('statement_number')->unique();
            $table->string('statement_type');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->json('opening_json');
            $table->json('activity_json');
            $table->json('closing_json');
            $table->unsignedInteger('entries_count');
            $table->string('entries_hash');
            $table->timestamp('generated_at')->index();
            $table->string('generated_by_type')->nullable();
            $table->string('generated_by_id')->nullable();
            $table->json('payload_json');
            $table->string('hash');
            $table->string('previous_hash')->nullable();
            $table->timestamps();

            $table->index(['statement_type', 'generated_at']);
            $table->index(['subject_type', 'subject_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_statement_snapshots');
    }
};
