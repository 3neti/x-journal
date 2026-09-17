<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_journal_heads', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('current_hash', 64)->nullable();
            $table->timestamps();
        });

        $latestIntegrity = DB::table('execution_journal_entries')
            ->orderByDesc('id')
            ->value('integrity');
        $latestIntegrity = is_string($latestIntegrity)
            ? json_decode($latestIntegrity, true)
            : $latestIntegrity;

        DB::table('execution_journal_heads')->insert([
            'id' => 1,
            'current_hash' => is_array($latestIntegrity) ? ($latestIntegrity['hash'] ?? null) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_journal_heads');
    }
};
