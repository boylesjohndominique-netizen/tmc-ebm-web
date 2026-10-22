<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->text('condition_notes')->nullable();
            $table->boolean('overdue_fee_paid')->nullable();
            $table->timestamp('admin_verified_at')->nullable();
            $table->foreignId('admin_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_verified_by');
            $table->dropColumn(['condition_notes', 'overdue_fee_paid', 'admin_verified_at', 'admin_remarks']);
        });
    }
};
