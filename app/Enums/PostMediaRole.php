<?php

namespace App\Enums;

enum PostMediaRole: string
{
    case Cover = 'cover';
    case Gallery = 'gallery';
    case Video = 'video';
    case Attachment = 'attachment';
}
