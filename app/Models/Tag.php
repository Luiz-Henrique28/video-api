<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $table = 'tag';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug'
    ];

    protected static function boot() 
    {
        parent::boot();

        static::creating( function($tag) {
            // definir qual vai ser a regra do slug (fazer isso quando for fazer as telas que tem conteudo de uma tag especifica)
            $tag->slug = Str::slug($tag->name);
        });

    }

    public function post(): BelongsToMany {
        return $this->belongsToMany(Post::class, 'post_tag');
    }

}
