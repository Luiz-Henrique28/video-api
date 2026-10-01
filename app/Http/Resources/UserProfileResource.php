<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    /**
     * Perfil publico de um usuario.
     * Inclui contadores de follow e is_following para o usuario autenticado.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authUser = $request->user('sanctum');

        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'avatar'          => $this->avatar,
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,
            'posts_count'     => $this->post()->count(),
            'is_following'    => $authUser ? $authUser->isFollowing($this->resource) : null,
        ];
    }
}
