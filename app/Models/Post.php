<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Like;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function isLikedBy(?User $user): bool
    {
        if (!$user) return false;
        return $this->likes()->where('user_id', $user->id)->exists();
    }

}
