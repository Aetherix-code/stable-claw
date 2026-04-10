<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('trigger_keywords')->nullable(); // ['toggl', 'time report']
            $table->json('steps')->nullable(); // structured workflow steps
            $table->longText('transcript')->nullable(); // raw training conversation
            $table->json('memory_keys')->nullable(); // memory keys this skill needs
            $table->foreignId('learned_from_conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
