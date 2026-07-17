<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('perfil_id')->nullable()->after('password')->constrained('perfiles')->nullOnDelete();
            $table->boolean('activo')->default(true)->after('perfil_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('perfil_id');
            $table->dropColumn('activo');
        });
    }
};
