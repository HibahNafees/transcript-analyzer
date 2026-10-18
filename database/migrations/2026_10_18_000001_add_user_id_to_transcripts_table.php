<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transcripts', function (Blueprint $table) {
            // Nullable so transcripts uploaded before accounts existed survive the migration;
            // `php artisan transcripts:claim {email}` hands them to an account. Unowned ones are visible to nobody.
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transcripts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
