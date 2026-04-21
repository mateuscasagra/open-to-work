<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->char('canonical_hash', 64)->unique();
            $table->string('title');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->text('description_html');
            $table->string('location')->nullable();
            $table->string('modality', 20)->nullable();
            $table->string('seniority', 20)->nullable();
            $table->json('stack');
            $table->integer('salary_min')->nullable();
            $table->integer('salary_max')->nullable();
            $table->string('salary_currency', 3)->nullable();
            $table->string('language', 10)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'posted_at']);
            $table->index('modality');
            $table->index('seniority');
        });

        Schema::create('job_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->string('source', 50);
            $table->string('external_id');
            $table->text('external_url');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['job_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_sources');
        Schema::dropIfExists('jobs');
    }
};
