<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use App\Models\Like;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    protected static function booted()
    {
        static::deleting(function (Post $post) {
            $post->media->each(function ($media) {
                $media->delete();
            });
            $post->comment()->delete();
        });

        static::deleted(function (Post $post) {
            Storage::disk(config('filesystems.default'))->deleteDirectory($post->mediaDirectory());
        });
    }

    protected $table = 'post';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'caption',
        'thumbnail_path',
        'visibility',
        'created_at',
        'updated_at'
    ];

    /**
     * The database stores the relative path; the API exposes the public URL.
     */
    protected function thumbnailPath(): Attribute
    {
        return Attribute::get(
            fn (?string $value) => Post::resolveStorageUrl($value)
        );
    }

    /**
     * Storage directory holding every file (media and thumbnail) of this post.
     */
    public function mediaDirectory(): string
    {
        return "uploads/users/{$this->user_id}/posts/{$this->id}";
    }

    /**
     * Builds the public URL for a stored path. Absolute URLs (external images)
     * are returned untouched.
     */
    public static function resolveStorageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk(config('filesystems.default'))->url($path);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    // pega apenas uma das imagens que servira de thumbnail
    public function firstMedia()
    {
        return $this->hasOne(Media::class)->oldest('id');
    }

    public function comment(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tag(): BelongsToMany 
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    /**
     * Restringe a consulta aos posts que o usuario pode ver:
     * posts publicos + posts do proprio usuario (inclusive privados).
     * Visitantes anonimos veem apenas posts publicos.
     */
    public function scopeWithMediaCounts(Builder $query): Builder
    {
        return $query->withCount([
            'media as image_count' => fn ($q) => $q->where('media_type', 'image'),
            'media as video_count' => fn ($q) => $q->where('media_type', 'video'),
        ]);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('visibility', 'public');

            if ($user) {
                $q->orWhere('user_id', $user->id);
            }
        });
    }

    public function isLikedBy(?User $user): bool
    {
        if (!$user) return false;
        return $this->likes()->where('user_id', $user->id)->exists();
    }

}
