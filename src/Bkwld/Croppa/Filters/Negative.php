<?php

namespace Bkwld\Croppa\Filters;

use GdThumb;
use Intervention\Image\Image;

/**
 * Class Negative
 *
 * @package Bkwld\Croppa\Filters
 */
class Negative implements FilterInterface
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
        $thumb->imageFilter(IMG_FILTER_NEGATE);
        $thumb->imageFilter(IMG_FILTER_CONTRAST, -50);

        return $thumb;
    }
}
