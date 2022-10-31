<?php

namespace Infira\Cachly;

use Infira\Cachly\Support\Collection;
use Infira\Cachly\Support\Helpers;
use Infira\Cachly\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Contracts\Cache\CallbackInterface;

class CacheInstance
{
    private AdapterManager $manager;

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function __construct(private readonly string $namespace, private readonly string $adapterName, protected AbstractAdapter $adapter)
    {
        $this->manager = new AdapterManager($namespace, $adapter);
    }

    private function createNewSubInstance(string $key): static
    {
        return new static($key, $this->adapterName, $this->adapter);
    }

    /**
     * Make sub instance
     */
    public function sub(string $key): static
    {
        $cKey = "$this->namespace.inherited__collection-$key";
        if(!$this->has($cKey)) {
            $this->putValue($cKey, []);
        }

        return $this->createNewSubInstance($cKey);
    }

    public function getCollections(): array
    {
        return $this->manager->collection()
            ->filter(fn($value, $key) => !str_contains($key, 'inherited__collection'))
            ->map(fn($value, string $key) => $this->createNewSubInstance($key))->all();
    }

    /**
     * Get all current instance/collection or collection cache items
     *
     * @return array
     */
    public function all(): array
    {
        return $this->manager->collection()->filter(fn($value, $key) => !str_contains($key, 'inherited__collection'))->all();
    }

    /**
     * loop over all items using $callback($value, $cacheKey, $cacheID)
     */
    public function each(callable $callback): void
    {
        $this->manager->collection()->each($callback);
    }

    /**
     * Run a map over each of the items.
     */
    public function map(callable $callback): Collection
    {
        return $this->manager->collection()->map($callback);
    }

    /**
     * Filter items using $callback($value,$cacheKey)
     */
    public function filter(callable $callback): Collection
    {
        return $this->manager->collection()->filter($callback);
    }

    /**
     * Filter items by regular expression against cache-key
     *
     */
    public function filterRegex(string $pattern): Collection
    {
        return $this->filter(static function($value, string $key) use ($pattern) {
            return (bool)preg_match($pattern, $key);
        });
    }

    /**
     * Set cache value
     *
     * @param string|int $key
     * @param mixed $value - value to store
     * @param int|string $expires - when expires. (int)0 - forever,(string)"10 hours" -  will be converted to time using strtotime(), (int)1596885301 - will tell when to expire. If $expires is in the past, it will be converted as forever
     * @return static
     */
    public function putValue(string|int $key, mixed $value, int|string $expires = 0): static
    {
        $id = Helpers::makeCacheID($key);
        $this->manager->registerKey($key, $id)->putValue($id, $value, $expires);

        return $this;
    }

    /**
     * Fetches a value from the pool or computes it if not found.
     *
     * On cache misses, a callback is called that should return the missing value.
     * This callback is given a PSR-6 CacheItemInterface instance corresponding to the
     * requested key, that could be used e.g. for expiration control. It could also
     * be an ItemInterface instance when its additional features are needed.
     *
     * @param string $key The key of the item to retrieve from the cache
     * @param callable|CallbackInterface $callback Should return the computed value for the given key/item
     * @param float|null $beta A float that, as it grows, controls the likeliness of triggering
     *                                              early expiration. 0 disables it, INF forces immediate expiration.
     *                                              The default (or providing null) is implementation dependent but should
     *                                              typically be 1.0, which should provide optimal stampede protection.
     *                                              See https://en.wikipedia.org/wiki/Cache_stampede#Probabilistic_early_expiration
     * @param array|null $metadata The metadata of the cached item {@see ItemInterface::getMetadata()}
     *
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException When $key is not valid or when $beta is negative
     */
    public function get(string $key, callable|CallbackInterface $callback, float $beta = null, array &$metadata = null): mixed
    {
        return $this->manager->get($key, $callback, $beta, $metadata);
    }

    /**
     * Get cache item
     *
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    public function getValue(string|int $key, mixed $default = null): mixed
    {
        return $this->manager->getValue(Helpers::makeCacheID($key), $default);
    }

    /**
     * Pass values the collection into a new class.
     */
    public function pipeInto(string|int $key, string $class, mixed $defaultValue = []): mixed
    {
        return new $class($this->getValue($key, $defaultValue));
    }

    /**
     * Get multiple items by keys
     *
     * @param array $keys - for examples ['key1','key2]
     * @return array - ['key1'=>'value1', 'key2'=>'value']
     */
    public function getMultipleValues(array $keys): array
    {
        $output = [];
        foreach($keys as $key) {
            $output[$key] = $this->getValue($key, null);
        }

        return $output;
    }

    /**
     * Does cache item exists
     *
     * @param string|int $key
     * @return bool
     */
    public function has(string|int $key): bool
    {
        return $this->manager->has(Helpers::makeCacheID($key));
    }

    /**
     * Is cache item expired
     *
     * @param string|int $key
     * @return bool
     */
    public function isExpired(string|int $key): bool
    {
        return $this->manager->isExpired(Helpers::makeCacheID($key));
    }

    /**
     * Tells when cache item expires
     *
     * @param string|int $key
     * @return integer - -1 = expired, 0 = never, >0 = timestamp
     */
    public function expiresAt(string|int $key): int
    {
        return $this->manager->expiration(Helpers::makeCacheID($key));
    }

    /**
     * Delete cache item
     * @param string|int|callable $key - $callable($cacheValue, $cacheKey):bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function forget(string|int|callable $key): void
    {
        if(is_callable($key)) {
            $this->each(function($cacheValue, $cacheKey) use ($key) {
                if($key($cacheValue, $cacheKey) === true) {
                    $this->manager->forget(Helpers::makeCacheID($cacheKey));
                }
            });
        }
        else {
            $this->manager->forget(Helpers::makeCacheID($key));
        }
    }

    /**
     * Delete by regular expression against cache key
     *
     * @param string $pattern
     * @return void
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function forgetByRegex(string $pattern): void
    {
        $this->filterRegex($pattern)->each(fn($cacheValue, $cacheKey) => $this->forget($cacheKey));
    }

    /**
     * Delete expired items
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function prune(): void
    {
        $this->forget(function($cacheValue, $cacheKey) {
            if($this->isExpired($cacheKey)) {
                $this->forget($cacheKey);
            }
        });
    }

    /**
     * Flush data on current instance/collection
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function clear(): void
    {
        $this->manager->clear();
    }

    /**
     * Execute $callback once by hash-sum of $parameters
     *
     * @param mixed ...$keys - will be used to generate hash sum ID for storing $callback result
     * @param callable $callback method result will be set to memory for later use
     * @return mixed - $callback result
     * @noinspection PhpDocSignatureInspection
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function once(...$keys): mixed
    {
        if(!$keys) {
            throw new InvalidArgumentException('parameters not defined');
        }
        /**
         * @var callable $callback
         */
        $callback = $keys[array_key_last($keys)];
        if(!is_callable($callback)) {
            throw new InvalidArgumentException('last parameter must be callable');
        }
        //if at least one key is provided then use only keys to make hashable
        if(count($keys) > 1) {
            $keys = array_slice($keys, 0, -1);
        }
        $CID = Helpers::makeCacheID($keys);

        return $this->manager->get($CID, $callback);
    }

    /**
     * Get cache keys
     *
     * @return array
     */
    public function getKeys(): array
    {
        return array_values($this->manager->getKeys());
    }

    public function getAdapter(): AbstractAdapter
    {
        return $this->adapter;
    }

    public function getAdapterName(): string
    {
        return $this->adapterName;
    }
}