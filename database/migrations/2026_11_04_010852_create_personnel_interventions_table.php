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
        Schema::create('personnel_interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->restrictOnDelete();
            $table->foreignId('intervention_package_id')->constrained('intervention_packages')->restrictOnDelete();
            $table->foreignId('bmi_assessment_id')->nullable()->constrained('bmi_assessments')->nullOnDelete();
            $table->date('assigned_on');
            $table->decimal('starting_weight_kg', 5, 2)->nullable();
            $table->decimal('target_weight_kg', 5, 2)->nullable();
            $table->date('next_follow_up_on')->nullable()->index();
            $table->string('status', 20)->default('ongoing')->index();
            $table->date('completed_on')->nullable();
            $table->text('outcome')->nullable();
            $table->text('remarks')->nullable();
            $table->text('health_service_notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personnel_interventions');
    }
};
