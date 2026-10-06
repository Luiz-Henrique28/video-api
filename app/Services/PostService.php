<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PostService
{
    public function __construct(private MediaUploadService $mediaUploads)
    {
    }

    /**
     * Creates a post with its tags and media atomically.
     * If the media upload fails, the post and any stored files are discarded.
     *
     * @param  array{caption?: ?string, visibility: string}  $attributes
     * @param  string[]  $tags
     * @param  UploadedFile[]  $files
     */
    public function create(User $user, array $attributes, array $tags, array $files): Post
    {
        $post = null;

        try {
            return DB::transaction(function () use ($user, $attributes, $tags, $files, &$post) {
                $post = Post::create($attributes + ['user_id' => $user->id]);

                $tagIds = collect($tags)->map(fn (string $name) => Tag::firstOrCreate(['name' => $name])->id);
                $post->tag()->attach($tagIds);

                $this->mediaUploads->store($post, $files);

                return $post->load('media');
            });
        } catch (\Throwable $e) {
            if ($post) {
                $this->mediaUploads->deleteDirectory($post);
            }

            throw $e;
        }
    }
}
