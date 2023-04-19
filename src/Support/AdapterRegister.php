<?php

namespace Infira\Cachly\Support;

use RuntimeException;
use stdClass;
use Symfony\Component\Cache\Adapter\AbstractAdapter;

/**
 * @internal
 */
final class AdapterRegister
{
    /**
     * @var stdClass[]
     */
    private static array $storage = [];

    public static function get(string $name, string $namespace): AbstractAdapter
    {
        if (!self::isRegistered($name)) {
            throw new RuntimeException("adapter named($name) is not registered");
        }
        $adapter = &self::$storage[$name];

        if ($adapter->isConstructed) {
            return $adapter->adapter;
        }
        $adapter->isConstructed = true;
        $adapter->adapter = ($adapter->constructor instanceof AbstractAdapter) ? $adapter->constructor : ($adapter->constructor)($namespace);

        return $adapter->adapter;
    }

    /**
     * @param  string  $name
     * @param  callable|string  $constructor
     */
    public static function register(string $name, callable|string $constructor): void
    {
        if (self::isRegistered($name)) {
            throw new RuntimeException("adapter($name) is already registered");
        }
        self::$storage[$name] = (object)['constructor' => $constructor, 'isConstructed' => false, 'adapter' => null];
    }

    public static function isRegistered(string $name): bool
    {
        return array_key_exists($name, self::$storage);
    }
}