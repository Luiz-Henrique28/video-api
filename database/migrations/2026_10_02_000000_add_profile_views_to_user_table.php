<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table) {
            // BIGINT para suportar perfis virais com bilhoes de visualizacoes
            $table->unsignedBigInteger('profile_views_count')->default(0)->after('following_count');
        });
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn('profile_views_count');
        });
    }
};
