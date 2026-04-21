<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('applications_count')->default(0);
            $table->unsignedInteger('responses_count')->default(0);
            $table->unsignedInteger('interviews_count')->default(0);
            $table->unsignedInteger('offers_count')->default(0);
            $table->unsignedInteger('rejections_count')->default(0);
            $table->json('breakdown')->nullable(); // por canal, por currículo, etc.
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics_daily');
    }
};
