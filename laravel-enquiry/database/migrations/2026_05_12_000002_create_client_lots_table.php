<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strata_client_id')->constrained('strata_clients')->cascadeOnDelete();
            $table->string('lot_number');
            $table->string('building');
            $table->string('plan_number')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_lots');
    }
};
