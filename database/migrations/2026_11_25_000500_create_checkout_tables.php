<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Checkout: abandoned-checkout leads, discount codes, and payment records.
// FR-PAY-01..12, FR-CRT-05, NFR-SEC-14, NFR-OPS-03. Money columns hold integer piasters.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 32);
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('governorate', 40)->nullable();
            $table->json('cart_snapshot')->nullable();
            $table->unsignedBigInteger('converted_order_id')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['store_id', 'phone']);
        });

        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('type', 20);
            $table->unsignedBigInteger('value')->default(0);
            $table->unsignedBigInteger('min_order_amount')->default(0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'code']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->string('gateway', 24);
            $table->string('mode', 10)->default('test');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('gateway_fee')->default(0);
            $table->unsignedBigInteger('cod_fee')->default(0);
            $table->string('external_reference', 128)->nullable();
            $table->string('webhook_event_id', 128)->nullable();
            $table->string('receipt_path', 500)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'gateway', 'webhook_event_id']);
            $table->index(['store_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('discount_codes');
        Schema::dropIfExists('leads');
    }
};
