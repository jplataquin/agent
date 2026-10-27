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
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->string('custom_header_name')->nullable()->after('auth_token');
            $table->timestamp('last_sync_at')->nullable()->after('tools_cache');
            $table->string('last_sync_status')->nullable()->after('last_sync_at'); // success, error
            $table->text('last_sync_error')->nullable()->after('last_sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->dropColumn([
                'custom_header_name',
                'last_sync_at',
                'last_sync_status',
                'last_sync_error',
            ]);
        });
    }
};
