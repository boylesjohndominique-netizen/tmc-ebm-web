<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_borrowing_days')->nullable();
            $table->unsignedSmallInteger('max_borrowing_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn(['min_borrowing_days', 'max_borrowing_days']);
        });
    }
};
