<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User Permission Management (Super Admin only).
 *
 * - users.is_super_admin: flags the one super admin account. Only super
 *   admins may edit permissions, and their own permissions cannot be
 *   edited by anyone.
 * - user_permissions: per-user overrides (module => [actions]).
 *   Absent row = module defaults apply.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('account_status');
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 50);
            $table->json('actions');
            $table->timestamps();
            $table->unique(['user_id', 'module']);
        });

        // The seeded administrator account becomes the super admin.
        DB::table('users')
            ->where('username', 'admin')
            ->update(['is_super_admin' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
