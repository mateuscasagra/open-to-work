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
            // Query de quota: WHERE user_id = X AND applied_at >= D. Os índices
            // existentes são (user_id, status) e applied_at isolado — nenhum cobre
            // bem essa query. Adicionando composto.
            $table->index(['user_id', 'applied_at'], 'applications_user_applied_idx');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('applications_user_applied_idx');
        });
    }
};
