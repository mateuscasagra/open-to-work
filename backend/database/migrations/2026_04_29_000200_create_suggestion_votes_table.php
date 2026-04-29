<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggestion_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('suggestion_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('value');
            $table->timestamps();

            $table->unique(['user_id', 'suggestion_id']);
            $table->index('suggestion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestion_votes');
    }
};
