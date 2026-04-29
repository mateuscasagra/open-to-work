<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('body');
            $table->unsignedInteger('upvotes_count')->default(0);
            $table->unsignedInteger('downvotes_count')->default(0);
            $table->integer('score')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index(['score', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestions');
    }
};
