<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catalog: products with AR/EN names, money in piasters, stock and status. FR-CAT-01
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->string('category', 80)->nullable();
            $table->unsignedBigInteger('price')->default(0);            // piasters
            $table->unsignedBigInteger('compare_at_price')->nullable(); // piasters
            $table->unsignedBigInteger('cost')->nullable();             // piasters
            $table->unsignedInteger('stock')->default(0);
            $table->string('status', 10)->default('draft');             // draft | live
            $table->boolean('has_options')->default(false);
            $table->json('colors')->nullable();                         // [{name, hex}]
            $table->json('images')->nullable();                         // paths on the public disk, cover first
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('source', 12)->default('manual');            // manual | note | photos | csv
            $table->unsignedInteger('sold_count')->default(0);          // filled by the Orders module
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'stock']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
