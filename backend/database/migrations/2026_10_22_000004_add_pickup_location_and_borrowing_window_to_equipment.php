<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('pickup_location', 255)->nullable();
            $table->date('borrowing_starts_on')->nullable();
            $table->date('borrowing_ends_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn(['pickup_location', 'borrowing_starts_on', 'borrowing_ends_on']);
        });
    }
};
