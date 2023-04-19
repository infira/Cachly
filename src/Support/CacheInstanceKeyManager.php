<?php

namespace Infira\Cachly\Support;


use Infira\Cachly\CacheInstance;
use Symfony\Component\Cache\CacheItem;

class CacheInstanceKeyManager
{
    private readonly CacheItem $item;

    public function __construct(private readonly CacheInstance $cache)
    {
        $this->item = $cache->getAdapter()->getItem("key-storage-".$this->cache->getNamespace());
    }

    public function register(string|array $key): static
    {
        $currentKeys = $this->all();
        $isDirty = false;
        foreach ((array)$key as $k) {
            if (!in_array($k, $currentKeys, true)) {
                $currentKeys[] = $k;
                $isDirty = true;
            }
        }
        if ($isDirty) {
            return $this->set($currentKeys);
        }

        return $this;
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->all(), true);
    }

    public function forget(string|array $key): static
    {
        $currentKeys = $this->all();
        $isDirty = false;
        foreach ((array)$key as $k) {
            if (($index = array_search($k, $currentKeys, true)) !== false) {
                unset($currentKeys[$index]);
                $isDirty = true;
            }
        }
        if ($isDirty) {
            return $this->set($currentKeys);
        }

        return $this;
    }

    public function all(): array
    {
        return array_values($this->item->get() ?: []);
    }

    public function clear(): static
    {
        return $this->set([]);
    }

    public function set(array $keys): static
    {
        $this->cache->saveDeferred($this->item->set($keys));

        return $this;
    }

    public function save(): void
    {
        $this->cache->commit();
    }
}