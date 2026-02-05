<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->string('name', 50)->nullable()->unique()->change();
        
            $table->string('avatar')->nullable()->change();
            $table->string('provider')->nullable()->change();
            $table->string('provider_id')->nullable()->change();
        });
    }
};
