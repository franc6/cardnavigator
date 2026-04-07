<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->longText('image_data')->nullable()->after('preference');
            $table->string('image_mime', 50)->nullable()->after('image_data');
            $table->string('color', 7)->nullable()->after('image_mime');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['image_data', 'image_mime', 'color']);
        });
    }
};
