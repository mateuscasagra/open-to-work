<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('jobs')->where('language', 'pt')->update(['language' => 'pt_BR']);
    }

    public function down(): void
    {
        DB::table('jobs')->where('language', 'pt_BR')->update(['language' => 'pt']);
    }
};
