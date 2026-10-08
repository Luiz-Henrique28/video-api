<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostDetailResource extends JsonResource
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
            'likes_count'    => (int) ($this->likes_count ?? 0),
            'image_count'    => (int) ($this->image_count ?? 0),
            'video_count'    => (int) ($this->video_count ?? 0),
            'is_liked'       => (bool) ($this->is_liked ?? false),
            'created_at'     => $this->created_at,
            'user'           => new UserMinifiedResource($this->whenLoaded('user')),
            'media'          => MediaResource::collection($this->whenLoaded('media')),
            'comment'        => CommentResource::collection($this->whenLoaded('comment')),
            'tag'            => TagResource::collection($this->whenLoaded('tag')),
        ];
    }
}
