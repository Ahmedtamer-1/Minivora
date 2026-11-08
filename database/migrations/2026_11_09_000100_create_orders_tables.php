<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Orders: customers, shipping rates per governorate, orders, their lines and every status change.
// FR-ORD-01..07, FR-ORD-10, FR-ORD-11. Money columns hold integer piasters.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('governorate', 40)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('address', 500)->nullable();
            $table->json('tags')->nullable();
            $table->string('notes', 2000)->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedBigInteger('total_spent')->default(0);     // piasters, delivered and active orders
            $table->timestamp('last_order_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'phone']);
            $table->index(['store_id', 'last_order_at']);
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('governorate', 40)->default('');
            $table->unsignedBigInteger('rate')->default(0);            // piasters
            $table->unsignedTinyInteger('delivery_days')->default(3);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'governorate']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number')->default(0);             // per store, starts at 1001
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('pending');
            $table->string('channel', 12)->default('website');         // website | instagram | whatsapp | phone | other
            $table->string('payment_method', 12)->default('cod');      // cod | card | wallet | instapay
            $table->string('customer_name')->default('');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('governorate', 40)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('address', 500)->nullable();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('shipping_fee')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->string('courier_name', 80)->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->text('notes')->nullable();                         // internal, never shown to the shopper
            $table->json('tags')->nullable();
            $table->boolean('stock_deducted')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'number']);
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'placed_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();     // Catalog id; no FK across modules
            $table->string('name')->default('');
            $table->string('variant', 80)->nullable();
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('unit_cost')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('line_total')->default(0);
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 12)->nullable();
            $table->string('to_status', 12)->default('pending');
            $table->string('actor_type', 10)->default('user');         // user | agent | system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('customers');
    }
};
