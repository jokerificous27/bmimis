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
        Schema::table('monthly_weight_monitorings', function (Blueprint $table) {
            // Set when the BMI of this weigh-in differs a lot from the last assessment: administrators are
            // asked whether the waist, hip and wrist should be measured again (pending, updated or declined).
            $table->string('measurement_review', 12)->nullable()->index()->after('ai_confidence');
            $table->decimal('bmi_change', 5, 2)->nullable()->after('measurement_review');
            $table->foreignId('baseline_assessment_id')->nullable()->after('bmi_change')->constrained('bmi_assessments')->nullOnDelete();
            $table->foreignId('measurement_reviewed_by')->nullable()->after('baseline_assessment_id')->constrained('users')->nullOnDelete();
            $table->timestamp('measurement_reviewed_at')->nullable()->after('measurement_reviewed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_weight_monitorings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('baseline_assessment_id');
            $table->dropConstrainedForeignId('measurement_reviewed_by');
            $table->dropColumn(['measurement_review', 'bmi_change', 'measurement_reviewed_at']);
        });
    }
};
