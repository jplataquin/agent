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
        Schema::create('token_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chat_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('completion_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost', 10, 6)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_metrics');
    }
};
