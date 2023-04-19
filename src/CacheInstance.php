<?php

namespace Infira\Cachly;

use DateInterval;
use DateTime;
use DateTimeInterface;
use Infira\Cachly\Exception\InvalidArgumentException;
use Infira\Cachly\Support\CacheInstanceKeyManager;
use Infira\Cachly\Support\Collection;
use Infira\Cachly\Support\Helpers;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CallbackInterface;

class CacheInstance
{
    public CacheInstanceKeyManager $keys;

    public function __construct(private readonly string $namespace, private readonly string $adapterName, protected AbstractAdapter $adapter)
    {
        $this->keys = new CacheInstanceKeyManager($this);
    }

    /**
     * Make sub instance
     *
     * @param  string  $key
     * @return $this
     */
    public function sub(string $key): static
    {
        return Cachly::instance("$this->namespace.sub-$key", $this->adapterName);
    }

    /**
     * Set cache value
     *
     * @param  string|int  $key
     * @param  mixed  $value  - value to store
     * @param  int|string|DateTimeInterface|null  $expires  (int)0|null - forever,(string)"10 hours" -  will be converted to time using strtotime(), (int)1596885301 - will tell when to expire. If $expires is in the past, it will be converted as forever
     * @return static
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function put(string|int $key, mixed $value, int|string|DateTimeInterface|DateInterval|null $expires = null): static
    {
        $this->keys->add($key)->save();
        $this->get($key, function (CacheItem $item) use ($value, $expires) {
            if (!empty($expires)) {
                if ($expires instanceof DateInterval || is_int($expires)) {
                    $item->expiresAfter($expires);
                }
                elseif ($expires instanceof DateTimeInterface) {
                    $item->expiresAt($expires);
                }
                else {
                    $item->expiresAt(new DateTime($expires));
                }
            }

            return $value;
        });

        return $this;
    }

    public function getItem(string $key): CacheItem
    {
        return $this->adapter->getItem($key);
    }

    /**
     * Fetches a value from the pool or computes it if not found.
     *
     * On cache misses, a callback is called that should return the missing value.
     * This callback is given a PSR-6 CacheItemInterface instance corresponding to the
     * requested key, that could be used e.g. for expiration control. It could also
     * be an ItemInterface instance when its additional features are needed.
     *
     * @param  string  $key  The key of the item to retrieve from the cache
     * @param  callable|CallbackInterface  $callback  Should return the computed value for the given key/item
     *
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException When $key is not valid or when $beta is negative
     */
    public function get(string $key, callable|CallbackInterface $callback): mixed
    {
        return $this->adapter->get($key, $callback);
    }

    /**
     * Execute $callback once by hash-sum of $keys
     * Note: last parameter must be callable
     *
     * @param  mixed  ...$keys  - will be used to generate hash sum ID for storing $callback result
     * @param  callable  $callback  method result will be set to memory for later use
     * @return mixed - $callback result
     */
    public function once(...$keys): mixed
    {
        if (!$keys) {
            throw new InvalidArgumentException('parameters not defined');
        }
        /**
         * @var callable $callback
         */
        $callback = $keys[array_key_last($keys)];
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('last parameter must be callable');
        }
        //if at least one key is provided then use only keys to make hashtable
        if (count($keys) > 1) {
            $keys = array_slice($keys, 0, -1);
        }

        return $this->get(Helpers::getId($keys), $callback);
    }

    /**
     * Get cache item value if not exists $default will be returned
     *
     * @param  string|int  $key
     * @param  mixed  $default
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getValue(string|int $key, mixed $default = null): mixed
    {
        if (!$this->keys->has($key)) {
            return $default;
        }
        if (!$this->adapter->hasItem($key)) {
            return $default;
        }

        return $this->getItem($key)->get();
    }

    /**
     * Does cache item exists
     *
     * @param  string|int  $key
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function has(string|int $key): bool
    {
        if (!$this->keys->has($key)) {
            return false;
        }

        return $this->adapter->hasItem($key);
    }

    /**
     * Is cache item expired
     *
     * @param  string|int  $key
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function isExpired(string|int $key): bool
    {
        if (!$this->keys->has($key)) {
            return true;
        }

        return !$this->adapter->hasItem($key);
    }

    /**
     * Delete cache item
     *
     * @param  string|int|callable  $key  - $callable($cacheValue, $cacheKey):bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function forget(string|int|callable $key): void
    {
        if (is_callable($key)) {
            $this->each(function ($cacheValue, $cacheKey) use ($key) {
                if ($key($cacheValue, $cacheKey) === true) {
                    $this->forget($cacheKey);
                }
            });
        }
        else {
            foreach ((array)$key as $k) {
                $this->keys->forget($k);
                $this->adapter->deleteItem($k);
            }
        }
        $this->keys->save();
    }

    /**
     * Delete by regular expression against cache key
     *
     * @param  string  $leyPattern
     * @return void
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function forgetByRegex(string $leyPattern): void
    {
        $this->filterRegex($leyPattern)->each(fn($cacheValue, $cacheKey) => $this->forget($cacheKey));
    }

    /**
     * Delete expired items
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function prune(): void
    {
        $this->forget(function ($cacheValue, $cacheKey) {
            if ($this->isExpired($cacheKey)) {
                $this->forget($cacheKey);
            }
        });
    }

    /**
     * Flush data on current instance/collection
     *
     */
    public function clear(): void
    {
        $this->adapter->clear($this->namespace);
        $this->keys->clear()->save();
    }

    /**
     * Get cache keys
     *
     * @return array
     */
    public function getKeys(): array
    {
        return array_values($this->keys->all());
    }

    /**
     * Get all items
     *
     * @return array
     */
    public function all(): array
    {
        $output = [];
        foreach ($this->getKeys() as $key) {
            if ($this->adapter->hasItem($key)) {
                $output[$key] = $this->getValue($key);
            }
        }

        return $output;
    }

    /**
     * @alias self::all()
     * @return array
     */
    public function toArray(): array
    {
        return $this->all();
    }

    /**
     * loop over all items using callable
     * Note: $callback($value, $cacheKey)
     *
     * @param  callable<string,mixed>  $callback
     * @return void
     */
    public function each(callable $callback): void
    {
        $this->collect()->each($callback);
    }

    /**
     * Run a map over each of the items.
     * Note: $callback($value, $cacheKey)
     *
     * @param  callable<string,mixed>  $callback
     * @return Collection
     */
    public function map(callable $callback): Collection
    {
        return $this->collect()->map($callback);
    }

    /**
     * Filter items using callable
     * Note: $callback($value, $cacheKey)
     *
     * @param  callable<string,mixed>  $callback
     * @return Collection
     */
    public function filter(callable $callback): Collection
    {
        return $this->collect()->filter($callback);
    }

    /**
     * Filter items by regular expression against cache-key
     *
     * @param  string  $keyPattern
     * @return Collection
     */
    public function filterRegex(string $keyPattern): Collection
    {
        return $this->filter(static fn($value, string $key) => (bool)preg_match($keyPattern, $key));
    }

    /**
     * Collection values into Collection
     *
     * @return Collection
     */
    public function collect(): Collection
    {
        return new Collection($this->toArray());
    }

    /**
     * Pass values the collection into a new class.
     *
     * @param  string|int  $key
     * @param  string  $class
     * @param  mixed  $defaultValue
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function pipeInto(string|int $key, string $class, mixed $defaultValue = []): mixed
    {
        return new $class($this->getValue($key, $defaultValue));
    }

    public function getAdapter(): AbstractAdapter
    {
        return $this->adapter;
    }

    public function getAdapterName(): string
    {
        return $this->adapterName;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    //region deprecated

    /**
     * @param  string|int  $key
     * @param  mixed  $value
     * @param  int|string|null  $expires
     * @return static
     * @throws \Psr\Cache\InvalidArgumentException
     * @see self::put()
     * @deprecated
     */
    public function putValue(string|int $key, mixed $value, int|string $expires = null): static
    {
        return $this->put(...func_get_args());
    }

    /**
     * Get multiple items by keys
     *
     * @param  array  $keys  - for examples ['key1','key2]
     * @return array - ['key1'=>'value1', 'key2'=>'value']
     * @throws \Psr\Cache\InvalidArgumentException
     * @deprecated - use self::filter() -> instead
     */
    public function getMultipleValues(array $keys): array
    {
        $output = [];
        foreach ($keys as $key) {
            $output[$key] = $this->getValue($key, null);
        }

        return $output;
    }
    //endregion
}