<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cart: persistent guest/customer carts, line items, and cart analytics events. FR-CRT-01..04
// Money columns hold integer piasters.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 100)->nullable();
            $table->string('discount_code', 64)->nullable();
            $table->timestamps();

            $table->index(['store_id', 'session_id']);
            $table->index(['store_id', 'user_id']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_snapshot')->default(0); // piasters
            $table->timestamps();

            $table->index(['store_id', 'cart_id']);
        });

        Schema::create('cart_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('event_type', 20);
            $table->integer('delta')->default(0);
            $table->unsignedInteger('quantity_after')->default(0);
            $table->unsignedBigInteger('unit_price')->default(0); // piasters
            $table->timestamps();

            $table->index(['store_id', 'cart_id']);
            $table->index(['store_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_events');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
