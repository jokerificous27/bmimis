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
        Schema::create('bmi_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bmi_standard_id')->constrained('bmi_standards')->cascadeOnDelete();
            $table->string('label');
            $table->string('category', 10);
            $table->decimal('min_bmi', 5, 2)->nullable();
            $table->decimal('max_bmi', 5, 2)->nullable();
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->string('sex', 10)->nullable();
            $table->boolean('requires_intervention')->default(false);
            $table->foreignId('intervention_package_id')->nullable()->constrained('intervention_packages')->nullOnDelete();
            $table->string('color', 20)->default('zinc');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bmi_classifications');
    }
};
