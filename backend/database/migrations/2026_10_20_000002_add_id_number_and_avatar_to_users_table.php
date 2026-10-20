<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'id_number')) {
                $table->string('id_number', 20)->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('users', 'profile_photo_path')) {
                $table->string('profile_photo_path')->nullable()->after('contact_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('users', 'id_number')) {
                $cols[] = 'id_number';
            }
            if (Schema::hasColumn('users', 'profile_photo_path')) {
                $cols[] = 'profile_photo_path';
            }
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
