<?php

declare(strict_types = 1);

namespace Centrex\Hr\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Centrex\Hr\Hr
 */
class Hr extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Centrex\Hr\Hr::class;
    }
}
