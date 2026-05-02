<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('event_type'); // created, sent, failed, retried, etc
            $table->json('event_data')->nullable(); // Event-specific data
            $table->string('triggered_by')->nullable(); // System, User, Job, etc
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamps();

            // Indexes for querying
            $table->index('notification_id');
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_events');
    }
};
