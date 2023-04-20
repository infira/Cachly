<?php

namespace Infira\Cachly\Support;

use RuntimeException;
use stdClass;
use Symfony\Component\Cache\Adapter\AbstractAdapter;

/**
 * @template TNamespace
 * @internal
 */
final class AdapterRegister
{
    /**
     * @var stdClass[]
     */
    private static array $storage = [];

    public static function make(string $name, string $namespace): AbstractAdapter
    {
        if (!self::isRegistered($name)) {
            throw new RuntimeException("adapter named($name) is not registered");
        }
        return self::$storage[$name]($namespace);
    }

    /**
     * @param  string  $name
     * @param  (callable(TNamespace):AbstractAdapter)  $constructor
     */
    public static function register(string $name, callable $constructor): void
    {
        if (self::isRegistered($name)) {
            throw new RuntimeException("adapter($name) is already registered");
        }
        self::$storage[$name] = $constructor;
    }

    public static function isRegistered(string $name): bool
    {
        return array_key_exists($name, self::$storage);
    }
}