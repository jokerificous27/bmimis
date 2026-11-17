<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Left, center and right view pictures of the personnel, uploaded by the personnel themselves
     * (or by staff) next to the profile picture.
     */
    public function up(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            $table->string('photo_left_path')->nullable()->after('photo_path');
            $table->string('photo_center_path')->nullable()->after('photo_left_path');
            $table->string('photo_right_path')->nullable()->after('photo_center_path');
        });
    }

    public function down(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            $table->dropColumn(['photo_left_path', 'photo_center_path', 'photo_right_path']);
        });
    }
};
