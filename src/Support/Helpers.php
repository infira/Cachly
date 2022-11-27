<?php

namespace Infira\Cachly\Support;


use Infira\Cachly\Cachly;
use Infira\Cachly\Exception\InvalidArgumentException;
use Wolo\Globals\Globals;
use Wolo\Globals\GlobalsCollection;
use Wolo\Hash;

class Helpers
{
    /**
     * Execute $callback once by hash-sum of $parameters
     *
     * @param  mixed  ...$keys  - will be used to generate hash sum ID for storing $callback result <br>
     * If $keys contains only callback then hash sum will be generated Closure signature
     * @param  callable  $callback  method result will be set to memory for later use
     * @return mixed - $callback result
     * @noinspection PhpDocSignatureInspection
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function once(...$keys): mixed
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return Globals::of('InfiraCachly')->once(...$keys);
    }

    /**
     * @param  string  $key
     * @return GlobalsCollection
     */
    public static function storage(string $key): GlobalsCollection
    {
        return Globals::of('InfiraCachly')->of($key);
    }

    public static function makeCacheID(mixed ...$key): string
    {
        if (!$key) {
            throw new InvalidArgumentException('Cache key cannot be empty');
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        return Hash::make(Cachly::getOpt('cacheIDHashAlgorithm'), ...$key);
    }
}