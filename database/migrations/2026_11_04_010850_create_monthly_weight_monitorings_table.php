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
        Schema::create('monthly_weight_monitorings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('weight_kg', 5, 2);
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->foreignId('height_source_assessment_id')->nullable()->constrained('bmi_assessments')->nullOnDelete();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->string('pnp_classification')->nullable();
            $table->string('pnp_category', 10)->nullable();
            $table->string('who_classification')->nullable();
            $table->string('who_category', 10)->nullable();
            $table->date('measured_on')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['personnel_id', 'year', 'month']);
            $table->index(['year', 'month']);
            $table->index(['unit_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_weight_monitorings');
    }
};
