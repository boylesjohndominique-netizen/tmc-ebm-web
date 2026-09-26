<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('available_quantity')->default(0);
            $table->string('condition')->default('Good');
            $table->string('status')->default('Available');
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('equipment'); }
};
