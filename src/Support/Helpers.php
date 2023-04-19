<?php

namespace Infira\Cachly\Support;


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

    /**
     * Make string for hashing
     *
     * @param  array  $keys
     * @return string
     */
    public static function makeKeyString(array $keys): string
    {
        $output = [];
        foreach ($keys as $value) {
            if ($value instanceof Serializable) {
                $value = self::varDump($value->serialize());
            }
            elseif (is_array($value) || $value instanceof stdClass) {
                $valueDump = [];
                foreach ((array)$value as $k => $v) {
                    $valueDump[self::makeKeyString($k)] = self::makeKeyString($v);
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

    public static function isCallable(mixed $value): bool
    {
        return is_callable($value) && !is_array($value);
    }
}