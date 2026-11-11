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
        Schema::create('bmi_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('rank_id')->nullable()->constrained('ranks')->nullOnDelete();
            $table->date('assessed_on');
            $table->unsignedTinyInteger('age_at_assessment')->nullable();
            $table->string('sex', 10)->nullable();

            $table->decimal('height_cm', 5, 1);
            $table->decimal('weight_kg', 5, 2);
            $table->decimal('waist_cm', 5, 1)->nullable();
            $table->decimal('hip_cm', 5, 1)->nullable();
            $table->decimal('wrist_cm', 4, 1)->nullable();

            $table->decimal('bmi', 5, 2);
            $table->decimal('waist_hip_ratio', 4, 2)->nullable();
            $table->string('ibw_method', 20);
            $table->decimal('ibw_min_kg', 5, 1)->nullable();
            $table->decimal('ibw_max_kg', 5, 1)->nullable();
            $table->decimal('weight_difference_kg', 5, 2)->nullable();
            $table->string('weight_action', 10)->nullable();

            $table->foreignId('pnp_classification_id')->nullable()->constrained('bmi_classifications')->nullOnDelete();
            $table->string('pnp_classification')->nullable();
            $table->string('pnp_category', 10)->nullable()->index();
            $table->foreignId('who_classification_id')->nullable()->constrained('bmi_classifications')->nullOnDelete();
            $table->string('who_classification')->nullable();
            $table->string('who_category', 10)->nullable();
            $table->boolean('requires_intervention')->default(false);
            $table->foreignId('intervention_package_id')->nullable()->constrained('intervention_packages')->nullOnDelete();

            $table->string('status', 20)->default('draft');
            $table->string('unit_representative_name')->nullable();
            $table->string('unit_representative_designation')->nullable();
            $table->string('health_service_representative_name')->nullable();
            $table->string('health_service_representative_designation')->nullable();
            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('certified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('certified_at')->nullable();
            $table->text('certification_remarks')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('bmi_assessments')->nullOnDelete();
            $table->text('remarks')->nullable();

            $table->string('photo_front_path')->nullable();
            $table->string('photo_right_path')->nullable();
            $table->string('photo_left_path')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['personnel_id', 'assessed_on']);
            $table->index(['status', 'assessed_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bmi_assessments');
    }
};
