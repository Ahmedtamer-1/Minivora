<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Store-wide custom CSS and extra theme settings (page width, type scale, buttons…). FR-BLD-03
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->text('custom_css')->nullable();
            $table->json('settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn(['custom_css', 'settings']);
        });
    }
};
