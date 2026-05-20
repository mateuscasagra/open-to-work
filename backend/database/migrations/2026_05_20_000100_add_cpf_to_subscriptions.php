<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // CPF (11) ou CNPJ (14), persistido apenas em dígitos (sem máscara).
            // Asaas exige cpfCnpj pra gerar cobrança PIX. Guardamos pra reuso
            // em re-assinaturas sem precisar pedir de novo ao user.
            $table->string('cpf', 14)->nullable()->after('asaas_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('cpf');
        });
    }
};
