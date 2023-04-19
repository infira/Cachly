<?php

namespace Infira\Cachly\Support;

use Infira\Cachly\CacheInstance;

/**
 * @internal
 */
final class CacheInstanceRegister
{
    /**
     * @var CacheInstance[]
     */
    private static array $storage = [];

    public static function get(string $namespace, string $adapterName): CacheInstance
    {
        $key = "$adapterName.$namespace";
        if (!isset(self::$storage[$key])) {
            self::$storage[$key] = new CacheInstance(
                $namespace,
                $adapterName,
                AdapterRegister::get($adapterName, $namespace)
            );
        }

        return self::$storage[$key];
    }
}