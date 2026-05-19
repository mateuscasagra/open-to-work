<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable()->after('location');
            $table->index('country_code', 'jobs_country_idx');
        });

        // Backfill determinístico por source: drivers país-específicos.
        DB::table('jobs')
            ->whereIn('id', DB::table('job_sources')->select('job_id')->where('source', 'github_vagas'))
            ->update(['country_code' => 'BR']);

        DB::table('jobs')
            ->whereIn('id', DB::table('job_sources')->select('job_id')->where('source', 'arbeitnow'))
            ->update(['country_code' => 'DE']);
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex('jobs_country_idx');
            $table->dropColumn('country_code');
        });
    }
};
