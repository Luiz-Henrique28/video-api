<?php

use App\Jobs\GenerateThumbFromVideo;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->disk = config('filesystems.default');
    Storage::fake($this->disk);
});

it('throws RuntimeException when video source file does not exist', function () {
    $job = new GenerateThumbFromVideo('non_existent.mp4', 'thumb.jpg', $this->disk);

    $job->handle();
})->throws(\RuntimeException::class, 'Vídeo de origem não encontrado');

it('clears post thumbnail_path and cleans up disk when job fails permanently', function () {
    $user = User::factory()->create();
    $post = Post::create([
        'user_id' => $user->id,
        'caption' => 'Test thumbnail failure',
        'visibility' => 'public',
        'thumbnail_path' => 'posts/1/thumbnail/thumb.jpg',
    ]);

    // Simula resíduo de arquivo no disco
    Storage::disk($this->disk)->put('posts/1/thumbnail/thumb.jpg', 'corrupted content');

    $job = new GenerateThumbFromVideo('posts/1/video.mp4', 'posts/1/thumbnail/thumb.jpg', $this->disk, $post->id);

    // Dispara o hook failed
    $job->failed(new \RuntimeException('FFmpeg crashed'));

    $post->refresh();
    expect($post->thumbnail_path)->toBeNull();
    expect(Storage::disk($this->disk)->exists('posts/1/thumbnail/thumb.jpg'))->toBeFalse();
});
