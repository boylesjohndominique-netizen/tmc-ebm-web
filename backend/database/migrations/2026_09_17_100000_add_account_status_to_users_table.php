<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            // Pending (student self-registered, awaiting admin verification)
            // | Active | Banned
            $table->string('account_status')->default('Pending')->after('password');
            $table->timestamp('verified_at')->nullable()->after('account_status');
            $table->foreignId('verified_by')->nullable()->after('verified_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('banned_at')->nullable()->after('verified_by');
            $table->text('ban_reason')->nullable()->after('banned_at');
        });

        // Staff/faculty/admin created by the super admin start active;
        // only student self-registrations begin as Pending.
        DB::table('users')->update(['account_status' => 'Active']);
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['account_status', 'verified_at', 'verified_by', 'banned_at', 'ban_reason']);
        });
    }
};
