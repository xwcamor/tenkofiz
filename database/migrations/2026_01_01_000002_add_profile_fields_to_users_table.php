<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('profile_id')->nullable()->after('password')->constrained('profiles')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('profile_id');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->string('timezone', 64)->nullable()->after('must_change_password')->comment('Preferred timezone; falls back to the company timezone');
            $table->string('locale', 5)->nullable()->after('timezone')->comment('Preferred UI language (es/en)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_id');
            $table->dropColumn(['is_active', 'must_change_password', 'timezone', 'locale']);
        });
    }
};
