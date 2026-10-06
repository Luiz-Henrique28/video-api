<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Gera o arquivo de thumbnail a partir de um vídeo.
 *
 * Responsabilidade única: extrair o frame e gravá-lo em $thumbnailRelativePath.
 * O caminho da thumbnail e a atualização do Post são definidos por quem despacha o job
 * (MediaController@store), para que o frontend receba a URL já na resposta.
 */
class GenerateThumbFromVideo implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $videoPath,
        public string $thumbnailRelativePath,
        public string $disk = 'public',
    )
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $storage = Storage::disk($this->disk);

        // Caminho absoluto do vídeo e da thumbnail
        $videoFullPath = $storage->path($this->videoPath);
        $thumbnailAbsolutePath = $storage->path($this->thumbnailRelativePath);

        // Criar diretório da thumbnail
        $storage->makeDirectory(dirname($this->thumbnailRelativePath));

        // Executar FFmpeg para extrair frame do segundo 2 do vídeo com flags corrigidas para evitar aviso
        exec(sprintf(
            'ffmpeg -y -i %s -ss 00:00:02 -frames:v 1 -update 1 %s',
            escapeshellarg($videoFullPath),
            escapeshellarg($thumbnailAbsolutePath)
        ));
    }
}
