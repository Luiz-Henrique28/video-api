<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateThumbFromVideo implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public int $postId,
        public string $videoPath,
    )
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $disk = config('filesystems.default');

        // Caminho absoluto do vídeo
        $videoFullPath = Storage::disk($disk)->path($this->videoPath);

        // Gerar nome e caminho da thumbnail
        $thumbnailName = "thumb_" . uniqid() . ".jpg";
        $thumbnailRelativePath = "uploads/users/{$this->userId}/posts/{$this->postId}/thumbnail/{$thumbnailName}";
        $thumbnailAbsolutePath = Storage::disk($disk)->path($thumbnailRelativePath);

        // Criar diretório da thumbnail
        Storage::disk($disk)->makeDirectory(dirname($thumbnailRelativePath));

        // Executar FFmpeg para extrair frame do segundo 2 do vídeo
        exec("ffmpeg -i \"{$videoFullPath}\" -ss 00:00:02 -vframes 1 \"{$thumbnailAbsolutePath}\"");

        // Atualizar thumbnail no banco
        $thumbnailUrl = Storage::disk($disk)->url($thumbnailRelativePath);
        $post = Post::findOrFail($this->postId);
        $post->update(['thumbnail_path' => $thumbnailUrl]);
    }
}
