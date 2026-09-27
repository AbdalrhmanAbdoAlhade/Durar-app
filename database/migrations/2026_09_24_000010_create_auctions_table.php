<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug')->unique();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('cover_image')->nullable();
            $table->json('metadata')->nullable(); // weight, origin, certification, etc.

            $table->decimal('starting_price', 12, 2);
            $table->decimal('current_price', 12, 2);
            $table->decimal('min_bid_increment', 12, 2)->default(0);

            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->enum('status', ['scheduled', 'active', 'ended', 'cancelled'])->default('scheduled');

            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
