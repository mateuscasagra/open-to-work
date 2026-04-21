<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category')->nullable();
            $table->json('aliases')->nullable();
            $table->timestamps();
        });

        Schema::create('profile_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->string('proficiency', 20)->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_skills');
        Schema::dropIfExists('skills');
    }
};
