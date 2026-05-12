<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('client_email');
            $table->string('client_name');
            $table->string('building_name')->nullable();
            $table->unsignedInteger('building_size')->nullable();
            $table->text('message');
            $table->string('status')->default('pending');
            $table->json('n8n_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
