<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow', function (Blueprint $table) {
            $table->unsignedBigInteger('follower_id');
            $table->unsignedBigInteger('following_id');
            $table->timestamp('created_at')->useCurrent();

            // PK composta: garante unicidade e lookup O(1)
            $table->primary(['follower_id', 'following_id']);

            // Indice para 'quem segue X?'
            $table->index('following_id', 'idx_follow_following');

            $table->foreign('follower_id')
                  ->references('id')->on('user')
                  ->onDelete('cascade');

            $table->foreign('following_id')
                  ->references('id')->on('user')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow');
    }
};
