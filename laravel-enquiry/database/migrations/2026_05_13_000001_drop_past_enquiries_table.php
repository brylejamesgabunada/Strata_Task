<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('past_enquiries');
    }

    public function down(): void
    {
        // The local Laravel RAG table was removed because RAG now lives in the n8n AI agent.
    }
};
