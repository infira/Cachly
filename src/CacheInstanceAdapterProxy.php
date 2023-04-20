<?php

namespace Infira\Cachly;

use Infira\Cachly\Support\Helpers;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Contracts\Cache\CallbackInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @mixin CacheInstance
 */
trait CacheInstanceAdapterProxy
{
    public function getAdapter(): AbstractAdapter
    {
        return $this->adapter;
    }

    public function getAdapterName(): string
    {
        return $this->adapterName;
    }

    /**
     * @param  string  $key
     * @param  mixed|null  $initialValue  - set value to CacheItem
     * @return CacheItem
     */
    public function getItem(string $key, mixed $initialValue = null): CacheItem
    {
        $this->keys->register($key);
        if (!isset($this->deferredSet[$key])) {
            if (func_num_args() === 2) {
                $this->deferredSet[$key] = new CacheItem(
                    $this,
                    $this->adapter->getItem($key),
                    $initialValue
                );
            }
            else {
                $this->deferredSet[$key] = new CacheItem(
                    $this,
                    $this->adapter->getItem($key)
                );
            }
        }

        return $this->deferredSet[$key];
    }

    /**
     * Alias for getItem
     *
     * @alias
     * @param  string  $key
     * @param  mixed|null  $initialValue
     * @return CacheItem
     */
    public function item(string $key, mixed $initialValue = null): CacheItem
    {
        return $this->getItem(...func_get_args());
    }

    /**
     * @return iterable<string, CacheItem>
     */
    public function getItems(array $keys): \Generator
    {
        $this->keys->register($keys);

        foreach ($this->adapter->getItems($keys) as $k => $item) {
            $this->deferredSet[$k] = new CacheItem($this, $item);
            yield $k => $this->deferredSet[$k];
        }
    }

    /**
     * When $value is not callable then acts as default value when item does not exist
     *
     * When $value is callable then fetches a value from the pool or computes it if not found.
     * On cache misses, a callback is called that should return the missing value.
     * This callback is given a PSR-6 CacheItemInterface instance corresponding to the
     * requested key, that could be used e.g. for expiration control. It could also
     * be an ItemInterface instance when its additional features are needed.
     *
     * @template T
     *
     * @param  string  $key  The key of the item to retrieve from the cache
     * @param  (callable(CacheItemInterface,bool):T)|CallbackInterface<T>|mixed  $value
     * @param  float|null  $beta  A float that, as it grows, controls the likeliness of triggering
     *                              early expiration. 0 disables it, INF forces immediate expiration.
     *                              The default (or providing null) is implementation dependent but should
     *                              typically be 1.0, which should provide optimal stampede protection.
     *                              See https://en.wikipedia.org/wiki/Cache_stampede#Probabilistic_early_expiration
     * @param  array|null  $metadata  The metadata of the cached item {@see ItemInterface::getMetadata()}
     *
     * @return T
     *
     *  When $key is not valid or when $beta is negative
     */
    public function get(string $key, mixed $value = null, float $beta = null, array &$metadata = null): mixed
    {
        $this->keys->register($key);
        $args = func_get_args();
        $c = count($args);
        if ($c === 1 || ($c === 2 && !Helpers::isCallable($value))) {
            return $this->getValue($key, $value);
        }

        $args[1] = fn(\Symfony\Component\Cache\CacheItem $baseItem, bool &$save) => $args[1](new CacheItem($this, $baseItem), $save);

        return $this->adapter->get(...$args);
    }

    /**
     * @alias self::forget()
     *
     * @see self::forget()
     */
    public function delete(string $key): bool
    {
        return $this->forget($key);
    }

    /**
     * @alias self::forget()
     *
     * @see self::forget()
     */
    public function deleteItem(string $key): bool
    {
        return $this->forget($key);
    }

    /**
     * @param  string[]  $keys
     * @alias self::forget()
     *
     * @see self::forget()
     */
    public function deleteItems(array $keys): bool
    {
        return $this->forget($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        if ($item instanceof CacheItem) {
            $item = $item->baseItem();
        }

        return $this->adapter->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if ($item instanceof CacheItem) {
            $item = $item->baseItem();
        }

        return $this->adapter->saveDeferred($item);
    }

    public function commit(): bool
    {
        foreach ($this->deferredSet as $item) {
            $item->defer();
        }
        $this->deferredSet = [];

        return $this->adapter->commit();
    }

    /**
     * Flush data on current instance/collection
     */
    public function clear(): bool
    {
        $this->deferredSet = [];

        return $this->adapter->clear();
    }
}