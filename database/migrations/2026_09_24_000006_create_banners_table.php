<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['normal', 'offer'])->default('normal');
            $table->string('title_ar')->nullable();
            $table->string('title_en')->nullable();
            $table->string('image');

            // normal banner -> polymorphic link to a category or a product (nullable, optional)
            $table->nullableMorphs('bannerable');

            // offer banner -> linked coupon (nullable, only used when type = offer)
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
