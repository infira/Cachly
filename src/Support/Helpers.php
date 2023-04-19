<?php

namespace Infira\Cachly\Support;


use Infira\Cachly\Exception\InvalidArgumentException;
use RuntimeException;
use Serializable;
use stdClass;

/**
 * @internal
 */
class Helpers
{
    /**
     * Dump variable into printable string
     *
     * @param  mixed  $var
     * @return string
     */
    public static function dump(mixed $var): string
    {
        if (is_array($var) || is_object($var)) {
            return print_r($var, true);
        }

        return self::varDump($var);
    }

    public static function varDump(mixed $var): string
    {
        ob_start();
        var_dump($var);

        return trim(ob_get_clean());
    }

    /**
     * Basically convert any value to string
     * Use this cases use this to
     *
     * @example md5(Hash::hashable(value)) , or hash('algo',Hash::hashable(value))
     * @param  mixed  ...$data
     * @return string
     */
    public static function hashable(...$data): string
    {
        $output = [];
        foreach ($data as $value) {
            if (is_callable($value)) {
                throw new RuntimeException('cant make callable to hashable');
            }
            if ($value instanceof Serializable) {
                $value = self::varDump($value->serialize());
            }
            elseif (is_array($value) || $value instanceof stdClass) {
                $valueDump = [];
                foreach ((array)$value as $k => $v) {
                    $valueDump[self::hashable($k)] = self::hashable($v);
                }
                $value = serialize($valueDump);
            }
            elseif (is_scalar($value)
                || is_null($value)
                || (is_object($value) && method_exists($value, '__toString'))) {
                $value = (string)$value;
            }
            $output[] = preg_replace('![\s]+!u', '', self::varDump($value));
        }

        return implode('-', $output);
    }

    public static function getId(mixed ...$key): string
    {
        if (!$key) {
            throw new InvalidArgumentException('Cache key cannot be empty');
        }

        return hash('crc32b', static::hashable(...$key));
    }
}