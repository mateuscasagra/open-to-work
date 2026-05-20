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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 20)->unique();   // 'free' | 'pro'
            $table->string('name', 50);
            $table->integer('price_cents');         // BRL em centavos (ex: 2500 = R$ 25,00)
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Seed inicial. Pra trocar o preço Pro, basta UPDATE direto no banco —
        // cache do Plan tem TTL 60s, então mudança aparece em até 1 minuto.
        DB::table('plans')->insert([
            [
                'slug' => 'free',
                'name' => 'Free',
                'price_cents' => 0,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'price_cents' => 2500,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
