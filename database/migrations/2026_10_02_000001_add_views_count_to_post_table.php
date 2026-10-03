<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post', function (Blueprint $table) {
            // BIGINT para suportar posts virais com bilhoes de visualizacoes
            $table->unsignedBigInteger('views_count')->default(0)->after('thumbnail_path');
        });
    }

    public function down(): void
    {
        Schema::table('post', function (Blueprint $table) {
            $table->dropColumn('views_count');
        });
    }
};
