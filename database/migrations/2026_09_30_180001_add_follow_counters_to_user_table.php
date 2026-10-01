<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->unsignedInteger('followers_count')->default(0)->after('avatar');
            $table->unsignedInteger('following_count')->default(0)->after('followers_count');
        });
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn(['followers_count', 'following_count']);
        });
    }
};
