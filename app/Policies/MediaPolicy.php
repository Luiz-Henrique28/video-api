<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    /**
     * Determine whether the user can delete the media.
     * Only the owner of the post to which the media belongs can delete it.
     */
    public function delete(User $user, Media $media): bool
    {
        return $media->post !== null && $media->post->user_id === $user->id;
    }
}
