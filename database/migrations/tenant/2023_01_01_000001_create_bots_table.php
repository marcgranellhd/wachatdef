<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('whatsapp_number');
            $table->string('whatsapp_id');
            $table->string('ai_model')->default('gpt-4o');
            $table->boolean('is_active')->default(true);
            $table->text('welcome_message')->nullable();
            $table->text('farewell_message')->nullable();
            $table->boolean('business_hours_only')->default(false);
            $table->json('business_hours')->nullable();
            $table->integer('max_context_messages')->default(10);
            $table->text('system_prompt');
            $table->json('api_credentials');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
