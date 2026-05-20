<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->string('event_id', 80)->primary();
            $table->string('source', 30);
            $table->string('event_type', 60);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['source', 'event_type', 'created_at'], 'webhook_logs_source_type_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
