<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Platform: stores and their domains. FR-ONB
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subdomain', 63)->unique();
            $table->string('default_locale', 2)->default('ar');
            $table->string('storefront_password')->nullable(); // hashed; "opening soon" gate (FR-ONB-06)
            $table->json('profile')->nullable();               // onboarding answers (FR-ONB-04)
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('suspended_at')->nullable();      // FR-ACC-08
            $table->string('suspended_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('store_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('host')->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
        Schema::dropIfExists('store_domains');
        Schema::dropIfExists('stores');
    }
};
