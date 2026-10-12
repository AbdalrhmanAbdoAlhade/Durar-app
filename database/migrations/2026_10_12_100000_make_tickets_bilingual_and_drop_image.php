<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->renameColumn('name', 'name_ar');
            $table->renameColumn('details', 'details_ar');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name_ar');
            $table->text('details_en')->nullable()->after('details_ar');
        });

        if (Schema::hasColumn('tickets', 'image')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }

        // الصفوف القديمة: ننسخ الاسم والتفاصيل العربي للإنجليزي مؤقتاً
        \Illuminate\Support\Facades\DB::statement(
            'UPDATE tickets SET name_en = name_ar, details_en = details_ar WHERE name_en IS NULL'
        );
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'details_en']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->renameColumn('name_ar', 'name');
            $table->renameColumn('details_ar', 'details');
            $table->string('image')->nullable();
        });
    }
};
