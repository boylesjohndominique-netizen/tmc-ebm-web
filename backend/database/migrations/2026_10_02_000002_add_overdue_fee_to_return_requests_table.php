<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->unsignedInteger('overdue_days')->nullable()->after('returned_at');
            $table->decimal('overdue_fee', 10, 2)->nullable()->after('overdue_days');
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropColumn(['overdue_days', 'overdue_fee']);
        });
    }
};
