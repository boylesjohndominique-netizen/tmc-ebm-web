<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrowing_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->string('status')->default('Pending');
            $table->string('condition_on_return')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('return_requests'); }
};
