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
        Schema::table('borrowing_requests', function (Blueprint $table) {
            $table->index(['status', 'pickup_status', 'expected_return_date'], 'borrowings_status_pickup_idx');
            $table->index(['user_id', 'status'], 'borrowings_user_status_idx');
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->index(['status', 'handover_status'], 'returns_status_handover_idx');
            $table->index(['user_id', 'status'], 'returns_user_status_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at', 'archived_at', 'hidden_at'], 'notifications_inbox_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['account_status', 'role_id'], 'users_status_role_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('borrowing_requests', function (Blueprint $table) {
            $table->dropIndex('borrowings_status_pickup_idx');
            $table->dropIndex('borrowings_user_status_idx');
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropIndex('returns_status_handover_idx');
            $table->dropIndex('returns_user_status_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_inbox_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_status_role_idx');
        });
    }
};
