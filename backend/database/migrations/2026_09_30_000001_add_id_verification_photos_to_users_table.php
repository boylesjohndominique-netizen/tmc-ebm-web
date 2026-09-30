<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Private disk paths (relative to storage/app/private), never URLs —
            // the images are streamed to authorized admins/user via endpoints.
            $table->string('id_front_path')->nullable()->after('email_code_expires_at');
            $table->string('id_back_path')->nullable()->after('id_front_path');
            $table->timestamp('id_submitted_at')->nullable()->after('id_back_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['id_front_path', 'id_back_path', 'id_submitted_at']);
        });
    }
};
