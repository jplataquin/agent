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
        Schema::create('llm_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider_type')->default('openai'); // openai, anthropic, gemini, openrouter, custom
            $table->text('api_key');
            $table->string('model');
            $table->string('base_url')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->integer('max_tokens')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_providers');
    }
};
