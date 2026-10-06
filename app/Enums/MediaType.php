<?php

namespace App\Enums;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';

    public static function fromMimeType(string $mimeType): self
    {
        return str_starts_with($mimeType, 'image/') ? self::Image : self::Video;
    }
}
