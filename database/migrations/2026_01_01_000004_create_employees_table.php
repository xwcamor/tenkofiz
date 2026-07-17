<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->string('document_number', 12)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->date('hire_date')->nullable();
            $table->longText('face_descriptor')->nullable()->comment('face-api.js 128-value vectors stored as JSON');
            $table->timestamp('biometric_consent_at')->nullable()->comment('When the employee accepted the biometric data privacy notice');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
