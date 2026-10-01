<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class UpdateFollowCounters implements ShouldQueue
{
    use Queueable;

    /**
     * @param int    $followerId  ID de quem seguiu/deixou de seguir
     * @param int    $followingId ID de quem foi seguido/deixou de ser seguido
     * @param string $action      'follow' | 'unfollow'
     */
    public function __construct(
        public readonly int $followerId,
        public readonly int $followingId,
        public readonly string $action,
    ) {}

    /**
     * Atualiza contadores desnormalizados e invalida o cache.
     * Roda em background para nao bloquear a request do usuario.
     */
    public function handle(): void
    {
        $increment = $this->action === 'follow' ? 1 : -1;

        // Incremento atomico: safe para concorrencia
        User::where('id', $this->followingId)
            ->increment('followers_count', $increment);

        User::where('id', $this->followerId)
            ->increment('following_count', $increment);

        // Invalida cache de ambos os perfis
        Cache::forget("user_profile:{$this->followerId}");
        Cache::forget("user_profile:{$this->followingId}");
    }
}
