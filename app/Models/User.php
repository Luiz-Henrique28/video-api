<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * App\Models\User
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $provider
 * @property string|null $provider_id
 * @property string|null $avatar
 * @property string $password
 * @property int $followers_count
 * @property int $following_count
 * @property int $profile_views_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'user';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'provider',
        'provider_id',
        'avatar',
        'password',
        'created_at',
        'updated_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function post(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comment(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function likedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'like');
    }

    // ── Follow Relationships ──────────────────────────────────────────────────

    /** Usuarios que ESTE usuario segue */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'follow',
            'follower_id',
            'following_id'
        )->withPivot('created_at');
    }

    /** Usuarios que seguem ESTE usuario */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'follow',
            'following_id',
            'follower_id'
        )->withPivot('created_at');
    }

    /**
     * Verifica se este usuario ja segue o usuario alvo.
     * Usa lookup direto na PK composta para evitar carregar a relation completa.
     */
    public function isFollowing(?User $target): bool
    {
        if (!$target || !$this->id) return false;

        return Follow::where('follower_id', $this->id)
                     ->where('following_id', $target->id)
                     ->exists();
    }
}
