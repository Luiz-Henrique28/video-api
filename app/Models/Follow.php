<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Follow extends Model
{
    // Sem id autoincrement: usamos PK composta (follower_id, following_id)
    public $incrementing = false;

    // Apenas created_at: seguir e um evento imutavel
    public $timestamps = false;

    protected $table = 'follow';

    protected $fillable = [
        'follower_id',
        'following_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function following(): BelongsTo
    {
        return $this->belongsTo(User::class, 'following_id');
    }
}
