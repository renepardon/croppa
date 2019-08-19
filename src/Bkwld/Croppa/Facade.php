<?php

namespace Bkwld\Croppa;

/**
 * Class Facade
 *
 * @package Bkwld\Croppa
 */
class Facade extends \Illuminate\Support\Facades\Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Helpers::class;
    }
}
