<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('past_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('inquiry_id')->unique();
            $table->string('category');
            $table->string('subcategory')->nullable();
            $table->string('urgency')->default('Medium');
            $table->string('client_status')->nullable();
            $table->string('summary', 600)->nullable();
            $table->text('original_message')->nullable();
            $table->text('recommended_action')->nullable();
            $table->text('suggested_response')->nullable();
            $table->text('previous_resolution')->nullable();
            $table->longText('page_content')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('past_enquiries');
    }
};
