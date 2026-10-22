<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowing_requests', function (Blueprint $table) {
            $table->string('transaction_code', 12)->nullable()->unique();
            $table->string('qr_token', 64)->nullable()->unique();
            $table->json('receipt_snapshot')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('pickup_starts_at')->nullable();
            $table->timestamp('pickup_ends_at')->nullable();
            $table->string('pickup_status')->nullable();
            $table->timestamp('pickup_processed_at')->nullable();
            $table->foreignId('pickup_processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('pickup_remarks')->nullable();
        });

        DB::table('borrowing_requests')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                do {
                    $code = (string) random_int(100000000000, 999999999999);
                } while (DB::table('borrowing_requests')->where('transaction_code', $code)->exists());

                DB::table('borrowing_requests')->where('id', $row->id)->update([
                    'transaction_code' => $code,
                    'qr_token' => bin2hex(random_bytes(32)),
                    'pickup_status' => in_array($row->status, ['Approved', 'Completed'], true) ? 'Received' : null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('borrowing_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_processed_by');
            $table->dropColumn([
                'transaction_code', 'qr_token', 'receipt_snapshot', 'approved_at',
                'pickup_starts_at', 'pickup_ends_at', 'pickup_status',
                'pickup_processed_at', 'pickup_remarks',
            ]);
        });
    }
};
