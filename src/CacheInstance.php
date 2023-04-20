<?php

namespace Infira\Cachly;

use DateInterval;
use DateTimeInterface;
use Infira\Cachly\Exception\InvalidArgumentException;
use Infira\Cachly\Support\CacheInstanceKeyManager;
use Infira\Cachly\Support\Collection;
use Infira\Cachly\Support\Helpers;
use Psr\Cache\InvalidArgumentException as PsrInvalidArgumentExceptionContract;
use Symfony\Component\Cache\Adapter\AbstractAdapter;

/**
 * @template TKey
 * @template TValue
 */
class CacheInstance
{
    use CacheInstanceAdapterProxy;

    public CacheInstanceKeyManager $keys;
    /**
     * @var CacheItem[]
     */
    private array $deferredSet = [];

    public function __construct(
        private readonly string $namespace,
        private readonly string $adapterName,
        protected AbstractAdapter $adapter
    ) {
        $this->keys = new CacheInstanceKeyManager($this);
    }

    public function __destruct()
    {
        //$this->commit();
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
     * Sets a cache value to be persisted later.
     * Note: use self::commit() to save
     *
     * @example single pair set('key1', 'value1')
     * @example set multiple key-value pair set(['key1'=> 'value1','key2' => 'value2'])
     * @param  array<TKey,TValue>  $values
     * @return array<TKey,CacheItem>
     * @throws PsrInvalidArgumentExceptionContract
     */
    public function setMany(array $values): array
    {
        $keys = array_keys($values);

        return array_map(
            fn(mixed $value, string $key) => $this->set($key, $value),
            $values,
            $keys
        );
    }

    /**
     * Sets a cache value to be persisted later.
     * Note: use self::commit() to save
     *
     * @param  string  $key
     * @param  mixed  $value  - value to store
     * @return CacheItem
     * @throws PsrInvalidArgumentExceptionContract
     */
    public function set(string $key, mixed $value): CacheItem
    {
        return $this->item(...func_get_args());
    }

    /**
     * Put cache value to bool and persists a cache item immediately.
     *
     * @param  string  $key
     * @param  mixed  $value  - value to store
     * @param  int|string|DateTimeInterface|DateInterval|null  $expires  @see CacheItem::expires()
     * @return CacheItem
     * @throws PsrInvalidArgumentExceptionContract
     * @throws \Exception
     */
    public function put(string $key, mixed $value, int|string|DateTimeInterface|DateInterval|null $expires = null): CacheItem
    {
        $item = $this->item($key, $value)->expires($expires);
        $item->commit();

        return $item;
    }

    /**
     * @template TParams -  (...$keys,callable $callback)
     * Execute $callback once by hash-sum of $keys method signature i
     * @see https://github.com/infira/Cachly#using-method-arguments-as-key
     * Note: last parameter must be callable
     *
     * @param  TParams  ...$params  - will be used to generate hash sum ID for storing $callback result
     * @return mixed - $callback result
     * @throws PsrInvalidArgumentExceptionContract
     */
    public function once(...$params): mixed
    {
        if (!$params) {
            throw new InvalidArgumentException('parameters not defined');
        }
        /**
         * @var callable $callback
         */
        $callback = $params[array_key_last($params)];
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('last parameter must be callable');
        }
        //if at least one key is provided then use only keys to make hashtable
        if (count($params) <= 1) {
            throw new InvalidArgumentException('Provide at least one non callable parameter');
        }
        $hash = hash('crc32b', Helpers::makeKeyString(array_filter($params, static fn($key) => !is_callable($key))));

        return $this->get('once.'.$hash, $callback);
    }

    /**
     * Get cache item value if not exists $default will be returned
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     * @throws PsrInvalidArgumentExceptionContract
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        if (isset($this->deferredSet[$key])) {
            return $this->deferredSet[$key]->get();
        }
        if (!$this->has($key)) {
            return $default;
        }

        return $this->adapter->getItem($key)->get();
    }

    /**
     * @param  array  $keys
     * @param  mixed|null  $default  - if default value is not set then on non-existing key will be not added
     * @return array
     * @throws PsrInvalidArgumentExceptionContract
     */
    public function getValues(array $keys, mixed $default = null): array
    {
        $hasDefaultValue = func_num_args() > 1;
        $output = [];
        foreach ($keys as $key) {
            if ($hasDefaultValue) {
                $output[$key] = $this->getValue($key, $default);
            }
            if (isset($this->deferredSet[$key])) {
                $output[$key] = $this->deferredSet[$key]->get();
            }
            elseif ($this->has($key)) {
                $output[$key] = $this->adapter->getItem($key)->get();
            }
        }

        return $output;
    }

    /**
     * Does cache item exists
     *
     * @param  string  $key
     * @return bool
     * @throws PsrInvalidArgumentExceptionContract
     */
    public function has(string $key): bool
    {
        if (isset($this->deferredSet[$key])) {
            return true;
        }
        if (!$this->keys->has($key)) {
            return false;
        }
        return $this->adapter->getItem($key)->isHit();
    }

    /**
     * Is cache item expired
     *
     * @param  string  $key
     * @return bool
     * @throws PsrInvalidArgumentExceptionContract
     */
    public function isExpired(string $key): bool
    {
        if (!$this->keys->has($key)) {
            return true;
        }

        return !$this->adapter->hasItem($key);
    }

    /**
     * Delete cache item
     *
     * @param  string|string[]|callable  $key  - $callable($cacheValue, $cacheKey):bool
     * @return bool
     * @throws PsrInvalidArgumentExceptionContract
     */
    public function forget(string|array|callable $key): bool
    {
        $deleteKeys = [];
        if (is_callable($key)) {
            $this->each(function ($cacheValue, $cacheKey) use ($key, &$deleteKeys) {
                if ($key($cacheValue, $cacheKey) === true) {
                    $deleteKeys[] = $cacheKey;
                }
            });
        }
        elseif (is_array($key)) {
            array_push($deleteKeys, ...array_values($key));
        }
        else {
            $deleteKeys = (array)$key;
        }

        if (!$deleteKeys) {
            return false;
        }
        foreach ($deleteKeys as $k) {
            if (isset($this->deferredSet[$k])) {
                unset($this->deferredSet[$k]);
            }
        }
        $this->keys->forget($deleteKeys);

        return $this->adapter->deleteItems($deleteKeys);
        //$this->keys->save();
    }

    /**
     * Delete by regular expression against cache key
     *
     * @param  string  $keyPattern
     * @return void
     * @throws PsrInvalidArgumentExceptionContract
     */
    public function forgetByRegex(string $keyPattern): void
    {
        $this->filterRegex($keyPattern)->each(fn($cacheValue, $cacheKey) => $this->forget($cacheKey));
    }

    /**
     * Delete expired items
     *
     * @throws PsrInvalidArgumentExceptionContract
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
     * Get cache keys
     *
     * @return array
     * @throws PsrInvalidArgumentExceptionContract
     */
    public function getKeys(): array
    {
        $output = [];
        $keys = $this->keys->all();
        $isDirty = false;
        foreach ($keys as $i => $key) {
            if (isset($this->deferredSet[$key])) {
                $output[] = $key;
            }
            elseif ($this->adapter->hasItem($key)) {
                $output[] = $key;
            }
            else {
                unset($keys[$i]);
                $isDirty = true;
            }
        }
        if ($isDirty) {
            $this->keys->set($keys);
        }

        return $output;
    }

    /**
     * Get all items
     *
     * @return array
     * @throws PsrInvalidArgumentExceptionContract
     */
    public function all(): array
    {
        return $this->getValues($this->getKeys());
    }

    /**
     * @alias self::all()
     * @return array
     * @throws PsrInvalidArgumentExceptionContract
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
     * @throws PsrInvalidArgumentExceptionContract
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
     * @throws PsrInvalidArgumentExceptionContract
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
     * @throws PsrInvalidArgumentExceptionContract
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
     * @throws PsrInvalidArgumentExceptionContract
     */
    public function filterRegex(string $keyPattern): Collection
    {
        return $this->filter(static fn($value, string $key) => (bool)preg_match($keyPattern, $key));
    }

    /**
     * Collection values into Collection
     *
     * @return Collection
     * @throws PsrInvalidArgumentExceptionContract
     */
    public function collect(): Collection
    {
        return new Collection($this->toArray());
    }

    /**
     * Pass values the collection into a new class.
     *
     * @param  string  $key
     * @param  string  $class
     * @param  mixed  $defaultValue
     * @return mixed
     * @throws PsrInvalidArgumentExceptionContract
     */
    public function pipeInto(string $key, string $class, mixed $defaultValue = []): mixed
    {
        return new $class($this->getValue($key, $defaultValue));
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    //region deprecated

    /**
     * @param  string  $key
     * @param  mixed  $value
     * @param  int|string|null  $expires
     * @return static
     * @throws PsrInvalidArgumentExceptionContract
     * @see self::put()
     * @deprecated
     */
    public function putValue(string $key, mixed $value, int|string $expires = null): static
    {
        $this->put(...func_get_args());

        return $this;
    }

    /**
     * Get multiple items by keys
     *
     * @param  array  $keys  - for examples ['key1','key2]
     * @return array - ['key1'=>'value1', 'key2'=>'value']
     * @throws PsrInvalidArgumentExceptionContract
     * @deprecated - use self::getValues() -> instead
     */
    public function getMultipleValues(array $keys): array
    {
        return $this->getValues($keys, null);
    }
    //endregion
}