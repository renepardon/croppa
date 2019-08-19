<?php

namespace Bkwld\Croppa\Filters;

use GdThumb;
use Intervention\Image\Image;

/**
 * Interface FilterInterface
 *
 * @package Bkwld\Croppa\Filters
 */
interface FilterInterface
{
    /**
     * @param GdThumb $thumb
     *
     * @return GdThumb|Image $thumb
     */
    public function applyFilter(GdThumb $thumb);
}
