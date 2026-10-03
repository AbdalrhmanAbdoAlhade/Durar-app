<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('classification')->nullable()->after('quantity');
            $table->string('hardness')->nullable()->after('classification');
            $table->string('origin_country', 2)->nullable()->after('hardness');
            $table->string('origin_details')->nullable()->after('origin_country');
            $table->decimal('weight', 10, 2)->nullable()->after('origin_details');
            $table->string('weight_unit', 20)->nullable()->default('قيراط')->after('weight');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'classification',
                'hardness',
                'origin_country',
                'origin_details',
                'weight',
                'weight_unit',
            ]);
        });
    }
};