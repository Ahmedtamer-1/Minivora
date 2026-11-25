<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Builder: pages, sections, themes, media library, and page version snapshots. FR-BLD-01..08
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 120);
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->boolean('is_home')->default(false);
            $table->string('status', 16)->default('draft'); // draft | published
            $table->boolean('is_ai_draft')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'slug']);
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'is_home']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('type', 40);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'page_id', 'sort_order']);
        });

        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('primary_color', 20)->default('#111827');
            $table->string('accent_color', 20)->default('#2563eb');
            $table->string('font_heading', 80)->default('Cairo');
            $table->string('font_body', 80)->default('Cairo');
            $table->string('corner_radius', 16)->default('md');
            $table->string('color_mode', 10)->default('light');
            $table->string('template', 32)->nullable();
            $table->timestamps();

            $table->unique(['store_id']);
        });

        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 20)->default('public');
            $table->string('path', 500);
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('alt_en', 255)->nullable();
            $table->string('alt_ar', 255)->nullable();
            $table->timestamps();

            $table->index(['store_id', 'mime_type']);
        });

        Schema::create('page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('snapshot');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['page_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_versions');
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('pages');
    }
};
