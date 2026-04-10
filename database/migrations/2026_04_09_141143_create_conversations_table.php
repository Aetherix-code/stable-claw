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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel')->default('web'); // web, telegram
            $table->string('telegram_chat_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->boolean('is_learn_mode')->default(false);
            $table->string('learn_mode_skill_name')->nullable();
            $table->timestamp('learn_mode_started_at')->nullable();
            $table->string('ai_provider')->nullable(); // overrides default
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
