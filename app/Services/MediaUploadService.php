<?php

namespace App\Services;

use App\Enums\MediaType;
use App\Jobs\GenerateThumbFromVideo;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaUploadService
{
    /**
     * Writes the files to disk and creates their media records.
     *
     * Only relative paths are persisted; URLs are built on output.
     * If anything throws, the caller is responsible for removing the post directory.
     *
     * @param  UploadedFile[]  $files
     */
    public function store(Post $post, array $files): void
    {
        $disk = $this->disk();
        $directory = $post->mediaDirectory();

        $mediaRows = [];
        $thumbnailPath = null;
        $firstVideoPath = null;

        foreach (array_values($files) as $index => $file) {
            $type = MediaType::fromMimeType($file->getMimeType());
            $fileName = uniqid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());

            $path = $file->storeAs($directory, $fileName, $disk);

            if ($path === false) {
                throw new \RuntimeException("Failed to store file: {$fileName}");
            }

            if ($type === MediaType::Image && !$thumbnailPath) {
                $thumbnailPath = $file->storeAs("{$directory}/thumbnail", "thumb_{$fileName}", $disk);
            } elseif ($type === MediaType::Video && !$firstVideoPath) {
                $firstVideoPath = $path;
            }

            $mediaRows[] = [
                'file_path' => $path,
                'media_type' => $type->value,
                'order' => $index,
            ];
        }

        if (!$thumbnailPath && $firstVideoPath) {
            $thumbnailPath = "{$directory}/thumbnail/thumb_" . uniqid() . '.jpg';

            GenerateThumbFromVideo::dispatch($firstVideoPath, $thumbnailPath, $disk)->afterCommit();
        }

        if ($thumbnailPath) {
            $post->update(['thumbnail_path' => $thumbnailPath]);
        }

        $post->media()->createMany($mediaRows);
    }

    public function deleteDirectory(Post $post): void
    {
        Storage::disk($this->disk())->deleteDirectory($post->mediaDirectory());
    }

    private function disk(): string
    {
        return config('filesystems.default');
    }
}
