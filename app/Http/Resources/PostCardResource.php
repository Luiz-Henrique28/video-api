<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostCardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'user_id'        => $this->user_id,
            'caption'        => $this->caption,
            'thumbnail_path' => $this->thumbnail_path,
            'views_count'    => (int) ($this->views_count ?? 0),
            'image_count'    => (int) ($this->image_count ?? 0),
            'video_count'    => (int) ($this->video_count ?? 0),
            'first_media'    => $this->whenLoaded('firstMedia', fn () => [
                'post_id'   => $this->firstMedia->post_id,
                'file_path' => $this->firstMedia->file_path,
            ]),
            'user'           => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'avatar' => $this->user->avatar,
            ]),
            'created_at'     => $this->created_at,
        ];
    }
}
