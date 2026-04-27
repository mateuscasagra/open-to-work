<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn('location');
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->char('country_code', 2)->nullable()->after('salary_currency');
            $table->string('postal_code', 20)->nullable()->after('country_code');
            $table->string('state_code', 10)->nullable()->after('postal_code');
            $table->string('state_name', 120)->nullable()->after('state_code');
            $table->string('city', 120)->nullable()->after('state_name');

            $table->index('country_code', 'profiles_country_idx');
            $table->index(['country_code', 'state_code'], 'profiles_country_state_idx');
            $table->index(['country_code', 'city'], 'profiles_country_city_idx');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropIndex('profiles_country_city_idx');
            $table->dropIndex('profiles_country_state_idx');
            $table->dropIndex('profiles_country_idx');
            $table->dropColumn([
                'country_code',
                'postal_code',
                'state_code',
                'state_name',
                'city',
            ]);
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->string('location')->nullable()->after('salary_currency');
        });
    }
};
