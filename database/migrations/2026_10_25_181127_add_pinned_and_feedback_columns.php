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
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->after('title');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('feedback')->nullable()->after('content'); // 'like', 'dislike', or null
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropColumn('is_pinned');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('feedback');
        });
    }
};
