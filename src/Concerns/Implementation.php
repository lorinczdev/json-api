<?php

declare(strict_types=1);

namespace TiMacDonald\JsonApi\Concerns;

use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiServerImplementation;

trait Implementation
{
    /**
     * @internal
     *
     * @var (callable(): JsonApiServerImplementation)|null
     */
    private static $serverImplementationResolver;

    /**
     * @api
     *
     * @param (callable(): JsonApiServerImplementation) $callback
     * @return void
     */
    public static function resolveServerImplementationUsing(callable $callback)
    {
        self::$serverImplementationResolver = $callback;
    }

    /**
     * @internal
     *
     * @return void
     */
    public static function resolveServerImplementationNormally()
    {
        self::$serverImplementationResolver = null;
    }

    /**
     * @internal
     *
     * @return (callable(Request): JsonApiServerImplementation)
     */
    public static function serverImplementationResolver()
    {
        return self::$serverImplementationResolver ?? fn (Request $request): JsonApiServerImplementation => new JsonApiServerImplementation('1.0');
    }
}
