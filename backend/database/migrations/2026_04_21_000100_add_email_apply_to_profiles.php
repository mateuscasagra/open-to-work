<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->boolean('email_apply_enabled')->default(false);
            $table->string('email_apply_message_mode', 10)->nullable();
            $table->text('email_apply_message_template')->nullable();
            $table->string('email_apply_resume_mode', 10)->nullable();
            $table->foreignId('email_apply_resume_id')->nullable()->constrained('resumes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('email_apply_resume_id');
            $table->dropColumn([
                'email_apply_enabled',
                'email_apply_message_mode',
                'email_apply_message_template',
                'email_apply_resume_mode',
            ]);
        });
    }
};
