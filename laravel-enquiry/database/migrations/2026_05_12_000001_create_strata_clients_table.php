<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strata_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->string('account_manager')->nullable();
            $table->date('since')->nullable();
            $table->boolean('portal_access')->default(false);
            $table->unsignedInteger('open_requests')->default(0);
            $table->string('levy_status')->default('unknown');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strata_clients');
    }
};
