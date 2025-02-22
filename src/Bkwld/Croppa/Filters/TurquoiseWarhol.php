<?php

namespace Bkwld\Croppa\Filters;

use GdThumb;
use Intervention\Image\Image;

/**
 * Class TurquoiseWarhol
 *
 * @package Bkwld\Croppa\Filters
 */
class TurquoiseWarhol implements FilterInterface
{
    /**
     * Applies filter to given thumbnail object.
     *
     * @param GdThumb $thumb
     *
     * @return GdThumb|Image
     */
    public function applyFilter(GdThumb $thumb)
    {
        $thumb->imageFilter(IMG_FILTER_GRAYSCALE);
        $thumb->imageFilter(IMG_FILTER_BRIGHTNESS, 80);
        $thumb->imageFilter(IMG_FILTER_COLORIZE, -137, -45, -73);

        return $thumb;
    }
}
