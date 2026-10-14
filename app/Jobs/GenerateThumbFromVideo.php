<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Gera o arquivo de thumbnail a partir de um vídeo de forma resiliente.
 *
 * Responsabilidade:
 * - Extrair um frame do vídeo usando FFmpeg (com busca rápida e fallback).
 * - Garantir resiliência com políticas explícitas de tentativas (tries), timeout e backoff.
 * - Registrar falhas explícitas e limpar referências órfãs caso todas as tentativas falhem.
 */
class GenerateThumbFromVideo implements ShouldQueue
{
    use Queueable;

    /**
     * Número de tentativas antes de mover para a fila de falhas (failed_jobs).
     */
    public int $tries = 3;

    /**
     * Tempo máximo de execução do job em segundos.
     * Invariante: deve ser menor que retry_after (90s) configurado em config/queue.php
     * para evitar que outro worker pegue o mesmo job antes de este terminar.
     */
    public int $timeout = 60;

    /**
     * Tempo de espera (backoff exponencial) entre tentativas: 5s, 15s, 30s.
     * Dá tempo para contenções transitórias de I/O de disco serem resolvidas.
     *
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 30];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $videoPath,
        public string $thumbnailRelativePath,
        public string $disk = 'public',
        public ?int $postId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $storage = Storage::disk($this->disk);

        if (!$storage->exists($this->videoPath)) {
            throw new \RuntimeException("Vídeo de origem não encontrado no disco '{$this->disk}': {$this->videoPath}");
        }

        $videoFullPath = $storage->path($this->videoPath);
        $thumbnailAbsolutePath = $storage->path($this->thumbnailRelativePath);

        // Garante que o diretório de destino existe
        $storage->makeDirectory(dirname($this->thumbnailRelativePath));

        // 1. Tentativa rápida: Input seeking no segundo 00:00:02 (-ss antes de -i)
        // Isso lê apenas os metadados do cabeçalho sem decodificar o vídeo desde o início.
        $process = new Process([
            'ffmpeg', '-y',
            '-ss', '00:00:02',
            '-i', $videoFullPath,
            '-frames:v', '1',
            '-update', '1',
            $thumbnailAbsolutePath,
        ]);
        $process->setTimeout($this->timeout);
        $process->run();

        // 2. Fallback: se falhou (ex: vídeo com duração menor que 2 segundos), tenta extrair no segundo 00:00:00
        if (!$process->isSuccessful() || !$storage->exists($this->thumbnailRelativePath)) {
            $fallbackProcess = new Process([
                'ffmpeg', '-y',
                '-ss', '00:00:00',
                '-i', $videoFullPath,
                '-frames:v', '1',
                '-update', '1',
                $thumbnailAbsolutePath,
            ]);
            $fallbackProcess->setTimeout($this->timeout);
            $fallbackProcess->run();

            if (!$fallbackProcess->isSuccessful() || !$storage->exists($this->thumbnailRelativePath)) {
                $errorOutput = trim($fallbackProcess->getErrorOutput() ?: $process->getErrorOutput());
                throw new \RuntimeException("Falha ao gerar thumbnail via FFmpeg: {$errorOutput}");
            }
        }
    }

    /**
     * Chamado automaticamente pelo Laravel quando todas as tentativas ($tries) se esgotarem.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error("GenerateThumbFromVideo esgotou as tentativas para o post #{$this->postId}: " . $exception?->getMessage(), [
            'video_path' => $this->videoPath,
            'thumbnail_path' => $this->thumbnailRelativePath,
            'post_id' => $this->postId,
            'exception' => $exception,
        ]);

        // Limpa o caminho de thumbnail no banco para o frontend não apontar para um arquivo inexistente (404 perpétuo)
        if ($this->postId) {
            $post = Post::find($this->postId);
            if ($post && $post->getRawOriginal('thumbnail_path') === $this->thumbnailRelativePath) {
                $post->update(['thumbnail_path' => null]);
            }
        }

        // Remove resíduo de arquivo incompleto/corrompido se existir no disco
        $storage = Storage::disk($this->disk);
        if ($storage->exists($this->thumbnailRelativePath)) {
            $storage->delete($this->thumbnailRelativePath);
        }
    }
}
