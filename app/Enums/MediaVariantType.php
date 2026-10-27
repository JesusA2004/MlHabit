<?php

namespace App\Enums;

enum MediaVariantType: string
{
    case Thumbnail = 'thumbnail';
    case OptimizedWebp = 'optimized_webp';
    case OptimizedAvif = 'optimized_avif';
    case Poster = 'poster';
    case Preview = 'preview';
}
