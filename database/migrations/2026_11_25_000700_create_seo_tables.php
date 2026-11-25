<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SEO engine: redirects, per-store SEO settings, and 404 logs.
// FR-SEO-01..24.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('from_path', 500);
            $table->string('to_path', 500);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_automatic')->default(false);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'from_path']);
        });

        Schema::create('seo_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('default_title_en', 120)->nullable();
            $table->string('default_title_ar', 120)->nullable();
            $table->string('default_description_en', 320)->nullable();
            $table->string('default_description_ar', 320)->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('gsc_verification_token', 128)->nullable();
            $table->text('robots_extra')->nullable();
            $table->timestamps();

            $table->unique(['store_id']);
        });

        Schema::create('not_found_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('path', 500);
            $table->string('referrer', 500)->nullable();
            $table->timestamp('hit_at');
            $table->timestamps();

            $table->index(['store_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_logs');
        Schema::dropIfExists('seo_settings');
        Schema::dropIfExists('redirects');
    }
};
