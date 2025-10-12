<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

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

    //protected $appends = ['file_url'];

    public function getFileUrlAttribute() 
    {
        
        return \Storage::disk('public')->url($this->file_path);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
