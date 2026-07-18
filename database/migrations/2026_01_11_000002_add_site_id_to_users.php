<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A user bound to a site only sees that site's data (employees, marks,
            // reports, requests). NULL = company-wide access (company/system admin).
            $table->foreignId('site_id')->nullable()->after('profile_id')
                ->constrained('sites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
        });
    }
};
