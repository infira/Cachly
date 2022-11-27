<?php

namespace Infira\Cachly\Item;


use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\AbstractAdapter;

/**
 * @mixin \Symfony\Component\Cache\CacheItem
 */
class CacheItem
{
    private \Symfony\Component\Cache\CacheItem $cacheItem;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(private readonly AbstractAdapter $adapter, private readonly string|int $key, private readonly mixed $defaultValue)
    {
        $this->resetCacheItem();
    }

    public function __call(string $name, array $arguments)
    {
        $ret = $this->cacheItem->$name(...$arguments);
        if ($ret instanceof \Symfony\Component\Cache\CacheItem) {
            return $this;
        }

        return $ret;
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return \Symfony\Component\Cache\CacheItem::$name(...$arguments);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function resetCacheItem(bool $set = null): void
    {
        if ($set === null) {
            $set = !$this->adapter->hasItem($this->key);
        }
        $this->cacheItem = $this->adapter->getItem($this->key);
        $set && $this->cacheItem->set($this->defaultValue);
    }

    public function save(callable $afterSave = null): bool
    {
        $saved = $this->adapter->save($this->cacheItem);
        $afterSave && $saved && $afterSave();

        return $saved;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function destroy(callable $afterDestroy = null): bool
    {
        $destroyed = $this->adapter->delete($this->getKey());
        $this->resetCacheItem(true);
        $afterDestroy && $destroyed && $afterDestroy();

        return $destroyed;
    }
}