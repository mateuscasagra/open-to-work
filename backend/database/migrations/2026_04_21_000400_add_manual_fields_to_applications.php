<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['job_id']);
            $table->dropUnique(['user_id', 'job_id']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedBigInteger('job_id')->nullable()->change();
            $table->foreign('job_id')->references('id')->on('jobs')->nullOnDelete();
            $table->string('manual_title', 255)->nullable()->after('job_id');
            $table->string('manual_company', 255)->nullable()->after('manual_title');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['job_id']);
            $table->dropColumn(['manual_title', 'manual_company']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedBigInteger('job_id')->nullable(false)->change();
            $table->foreign('job_id')->references('id')->on('jobs')->cascadeOnDelete();
            $table->unique(['user_id', 'job_id']);
        });
    }
};
