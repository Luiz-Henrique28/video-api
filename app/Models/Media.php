<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    protected static function booted()
    {
        static::deleting(function (Media $media) {
            $path = $media->getRawOriginal('file_path');

            if ($path) {
                Storage::disk(config('filesystems.default'))->delete($path);
            }
        });
    }

    protected $table = 'media';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'file_path',
        'media_type',
        'order',
        'created_at',
        'updated_at'
    ];

    /**
     * The database stores the relative path; the API exposes the public URL.
     */
    protected function filePath(): Attribute
    {
        return Attribute::get(
            fn (?string $value) => Post::resolveStorageUrl($value)
        );
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
