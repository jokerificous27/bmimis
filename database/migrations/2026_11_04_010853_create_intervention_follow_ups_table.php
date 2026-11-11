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
        Schema::create('intervention_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_intervention_id')->constrained('personnel_interventions')->cascadeOnDelete();
            $table->date('follow_up_on');
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intervention_follow_ups');
    }
};
