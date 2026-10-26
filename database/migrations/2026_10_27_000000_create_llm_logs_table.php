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
        Schema::create('llm_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('llm_provider_id')->constrained('llm_providers')->cascadeOnDelete();
            $table->foreignId('chat_session_id')->nullable()->constrained('chat_sessions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->default('chat_completion'); // chat_completion, test_prompt, connection_test
            $table->string('endpoint', 500);
            $table->string('http_method', 10)->default('POST');
            $table->integer('status_code')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->longText('request_payload')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->boolean('is_success')->default(false);
            $table->timestamps();

            $table->index(['llm_provider_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_logs');
    }
};
