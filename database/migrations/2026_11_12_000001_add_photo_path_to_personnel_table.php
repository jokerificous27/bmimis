<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identification picture of the personnel, shown at the Weigh-in Station when their QR code is scanned.
     */
    public function up(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
