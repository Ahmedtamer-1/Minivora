<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catalog extensions: categories, variants, reviews, wishlists, static pages, contact messages, and extra product fields.
// FR-CAT-02, FR-CAT-03, FR-CAT-08, FR-CAT-10, FR-CAT-11, FR-CAT-13, FR-CAT-17, FR-CAT-18
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug');
            $table->string('image')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'slug']);
            $table->index(['store_id', 'sort_order']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->nullable();
            $table->json('options');
            $table->unsignedBigInteger('price');              // piasters
            $table->unsignedBigInteger('cost')->nullable();   // piasters
            $table->unsignedInteger('stock')->default(0);
            $table->json('images')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'product_id']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('customer_name');
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->boolean('approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'product_id', 'approved']);
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_id', 100)->nullable();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'user_id']);
            $table->index(['store_id', 'session_id']);
        });

        Schema::create('static_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 64);
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->text('body')->nullable();
            $table->text('body_ar')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'slug']);
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'read_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('category');
            $table->string('slug', 160)->nullable()->after('name_ar');
            $table->string('sku', 64)->nullable()->after('slug');
            $table->string('video_url', 500)->nullable()->after('images');
            $table->unsignedInteger('reorder_threshold')->default(5)->after('stock');

            $table->index(['store_id', 'category_id']);
            $table->index(['store_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'category_id']);
            $table->dropIndex(['store_id', 'slug']);
            $table->dropColumn(['category_id', 'slug', 'sku', 'video_url', 'reorder_threshold']);
        });

        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('static_pages');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('categories');
    }
};
