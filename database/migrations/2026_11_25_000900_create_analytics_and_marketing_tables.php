<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Analytics & Marketing: visit sessions, page views, tracking pixels, newsletter subscribers,
// abandoned checkouts, ad spend, campaigns, and daily campaign metrics.
// FR-ANA-01..06, FR-MKT-01..05, FR-MI-01..08. Money columns hold integer piasters.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('session_key', 100);
            $table->string('utm_source', 80)->nullable();
            $table->string('utm_medium', 80)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('device_type', 20)->default('mobile');
            $table->timestamp('started_at');
            $table->timestamps();

            $table->unique(['store_id', 'session_key']);
        });

        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_session_id')->nullable()->constrained('visit_sessions')->nullOnDelete();
            $table->string('path', 500);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('utm_source', 80)->nullable();
            $table->string('utm_medium', 80)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            $table->string('device_type', 20)->default('mobile');
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->index(['store_id', 'viewed_at']);
        });

        Schema::create('pixels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('meta_pixel_id', 64)->nullable();
            $table->text('meta_capi_token')->nullable();
            $table->string('meta_ad_account_id', 64)->nullable();
            $table->string('tiktok_pixel_id', 64)->nullable();
            $table->string('snap_pixel_id', 64)->nullable();
            $table->string('ga4_measurement_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['store_id']);
        });

        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('email', 255);
            $table->string('name', 255)->nullable();
            $table->string('source', 40)->default('storefront');
            $table->timestamp('subscribed_at');
            $table->timestamps();

            $table->unique(['store_id', 'email']);
        });

        Schema::create('abandoned_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('customer_name', 255)->nullable();
            $table->string('phone', 32);
            $table->json('items');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('recovered_order_id')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_spends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 32)->default('meta');
            $table->string('campaign_name', 160);
            $table->date('spend_date');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 100)->nullable();
            $table->string('channel', 24)->default('meta');
            $table->string('name', 200);
            $table->string('utm_campaign', 120)->nullable();
            $table->string('status', 24)->default('active');
            $table->boolean('is_proposal')->default(false);
            $table->json('proposal_payload')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedBigInteger('spend')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->double('frequency')->default(0);
            $table->unsignedInteger('meta_purchases')->default(0);
            $table->unsignedBigInteger('meta_purchase_value')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'campaign_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_metrics');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('ad_spends');
        Schema::dropIfExists('abandoned_checkouts');
        Schema::dropIfExists('subscribers');
        Schema::dropIfExists('pixels');
        Schema::dropIfExists('page_views');
        Schema::dropIfExists('visit_sessions');
    }
};
