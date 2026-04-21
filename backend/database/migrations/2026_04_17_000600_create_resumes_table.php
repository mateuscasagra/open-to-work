<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('language', 10)->default('pt_BR');
            $table->boolean('is_pdf_upload')->default(false);
            $table->string('file_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('resume_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50); // experience, education, skill, language, project, summary
            $table->unsignedSmallInteger('order')->default(0);
            $table->json('content');
            $table->timestamps();

            $table->index(['resume_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_sections');
        Schema::dropIfExists('resumes');
    }
};
