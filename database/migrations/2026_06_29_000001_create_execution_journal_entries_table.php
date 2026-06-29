<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_number')->unique();
            $table->string('event_type')->index();
            $table->timestamp('occurred_at')->index();
            $table->json('actor');
            $table->json('subject');
            $table->json('money')->nullable();
            $table->json('references');
            $table->json('payload');
            $table->json('integrity');
            $table->json('metadata');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_journal_entries');
    }
};
