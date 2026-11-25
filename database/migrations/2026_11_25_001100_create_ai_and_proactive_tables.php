<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// AI management team approvals and proactive insights feed. FR-AI-07, FR-PRO-01..06
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('agent', 32);
            $table->string('function_name', 80);
            $table->json('payload');
            $table->bigInteger('estimated_impact')->default(0);
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

        Schema::create('insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('agent', 32);
            $table->string('cadence', 16)->default('daily');
            $table->string('type', 40);
            $table->unsignedTinyInteger('urgency')->default(50);
            $table->bigInteger('estimated_impact')->default(0);
            $table->double('score')->default(0);
            $table->string('title', 255);
            $table->text('summary');
            $table->json('evidence');
            $table->json('recommended_action')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('snoozed_until')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insights');
        Schema::dropIfExists('approvals');
    }
};
