<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Core: append-only, hash-chained activity log. FR-ACC-05, NFR-SEC-10
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->index(); // null = platform-level action; no FK so logs outlive stores
            $table->string('actor_type', 10);                    // user | agent | system
            $table->unsignedBigInteger('actor_id')->nullable();  // user id (or the user an agent acted for)
            $table->string('actor_name')->nullable();            // agent name, e.g. "cfo"
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip', 45)->nullable();
            $table->char('prev_hash', 64);
            $table->char('hash', 64);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
