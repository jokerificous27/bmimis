<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique()->after('employee_number');
        });

        Schema::table('monthly_weight_monitorings', function (Blueprint $table) {
            $table->string('capture_method', 20)->default('manual')->after('remarks');
            $table->string('photo_path')->nullable()->after('capture_method');
            $table->decimal('ai_weight_kg', 5, 2)->nullable()->after('photo_path');
            $table->string('ai_confidence', 10)->nullable()->after('ai_weight_kg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_weight_monitorings', function (Blueprint $table) {
            $table->dropColumn(['capture_method', 'photo_path', 'ai_weight_kg', 'ai_confidence']);
        });

        Schema::table('personnel', function (Blueprint $table) {
            $table->dropUnique(['qr_token']);
            $table->dropColumn('qr_token');
        });
    }
};
