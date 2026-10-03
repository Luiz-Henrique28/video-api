<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class IncrementProfileViews implements ShouldQueue
{
    use Queueable;

    /**
     * @param int         $profileUserId  ID do dono do perfil que foi visitado
     * @param int|null    $visitorUserId  ID do visitante logado (null se anonimo)
     * @param string|null $visitorIp      IP do visitante (fallback para anonimos)
     */
    public function __construct(
        public readonly int $profileUserId,
        public readonly ?int $visitorUserId,
        public readonly ?string $visitorIp,
    ) {}

    /**
     * Incrementa o contador de visualizacoes de perfil com deduplicacao.
     *
     * Usa Cache para garantir que o mesmo visitante (por User ID ou IP)
     * so conte como 1 visualizacao a cada 24 horas por perfil.
     * Isso previne inflacao artificial por bots ou recarregamentos.
     */
    public function handle(): void
    {
        // Ignora visualizacoes do proprio dono do perfil
        if ($this->visitorUserId && $this->visitorUserId === $this->profileUserId) {
            return;
        }

        // Identificador unico do visitante: prefire user ID (mais preciso) ou IP
        $identifier = $this->visitorUserId
            ? "user:{$this->visitorUserId}"
            : "ip:{$this->visitorIp}";

        // Chave de deduplicacao: expira em 24h (86400 segundos)
        $dedupeKey = "profile_view_seen:{$this->profileUserId}:{$identifier}";

        // Se ja visualizou nas ultimas 24h, ignora
        if (Cache::has($dedupeKey)) {
            return;
        }

        // Marca como visto por 24h
        Cache::put($dedupeKey, true, 86400);

        // Incremento atomico no banco (seguro para concorrencia)
        User::where('id', $this->profileUserId)
            ->increment('profile_views_count');
    }
}
