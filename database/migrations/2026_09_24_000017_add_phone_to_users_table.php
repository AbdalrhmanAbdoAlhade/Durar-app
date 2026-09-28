<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some projects already have `users.phone` (from their own users migration).
        // Only add it when it is really missing, so this migration never collides.
        if (Schema::hasColumn('users', 'phone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        // Intentionally a no-op: we can't know whether this migration or the
        // project's own users migration created the column, and dropping a
        // pre-existing column on rollback would be destructive.
    }
};
