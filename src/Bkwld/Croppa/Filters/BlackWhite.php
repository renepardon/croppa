<?php

namespace Bkwld\Croppa\Filters;

use GdThumb;
use Intervention\Image\Image;

/**
 * Class BlackWhite
 *
 * @package Bkwld\Croppa\Filters
 */
class BlackWhite implements FilterInterface
{
    /**
     * Applies filter to given thumbnail object.
     *
     * @param \GdThumb $thumb
     *
     * @return GdThumb|Image
     */
    public function applyFilter(GdThumb $thumb)
    {
        return $thumb->imageFilter(IMG_FILTER_GRAYSCALE);
    }
}
