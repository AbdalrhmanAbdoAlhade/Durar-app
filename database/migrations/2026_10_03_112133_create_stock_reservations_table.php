<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
     Schema::create('stock_reservations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('quantity');
    $table->enum('status', ['reserved', 'committed', 'released'])->default('reserved');
    $table->timestamp('expires_at');
    $table->timestamps();

    $table->index(['product_id', 'status']);
    $table->index(['order_id', 'status']);
    $table->index('expires_at');
});
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
