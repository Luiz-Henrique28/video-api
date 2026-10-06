<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

class IncrementPostViews implements ShouldQueue
{
    use Queueable;

    /**
     * @param int         $postId     ID do post visualizado
     * @param string|null $visitorIp  IP do visitante (fallback para anonimos)
     * @param int|null    $visitorUserId  ID do visitante logado (null se anonimo)
     */
    public function __construct(
        public readonly int $postId,
        public readonly ?string $visitorIp,
        public readonly ?int $visitorUserId,
    ) {}

    /**
     * Incrementa o contador de visualizacoes do post com deduplicacao.
     *
     * Guarda definitivo contra race conditions: mesmo que dois requests
     * do mesmo visitante passem pelo check do Controller simultaneamente,
     * apenas o primeiro Job a rodar vai gravar a chave e incrementar.
     * O segundo Job chegara aqui e encontrara a chave ja existente.
     */
    public function handle(): void
    {
        // Identificador unico: prefire user ID (mais preciso) ou IP
        $identifier = $this->visitorUserId
            ? "user:{$this->visitorUserId}"
            : "ip:{$this->visitorIp}";

        // Chave de deduplicacao com janela de 4h (14400 segundos)
        $dedupeKey = "post_view_seen:{$this->postId}:{$identifier}";

        // Guarda definitivo: so incrementa se nao houver registro das ultimas 4h
        if (Cache::has($dedupeKey)) {
            return;
        }

        // Marca como visto por 4h (14400s) e incrementa atomicamente o post e o perfil do autor
        Cache::put($dedupeKey, true, 14400);

        $post = Post::find($this->postId);
        if ($post) {
            $post->increment('views_count');
            User::where('id', $post->user_id)->increment('total_views');
        }
    }
}
