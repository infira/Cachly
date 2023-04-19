<?php

namespace Infira\Cachly\Support;


use Infira\Cachly\CacheInstance;
use Symfony\Component\Cache\CacheItem;

class CacheInstanceKeyManager
{
    private readonly CacheItem $item;

    public function __construct(private readonly CacheInstance $cache)
    {
        $this->item = $cache->getItem("key-storage-".$this->cache->getNamespace());
    }

    public function add(string $key): static
    {
        if ($this->has($key)) {
            return $this;
        }
        $array = $this->all();
        $array[] = $key;
        $this->item->set($array);

        return $this;
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->all(), true);
    }

    public function forget(string $key): static
    {
        if ($this->has($key)) {
            $array = $this->all();
            unset($array[array_search($key, $array, true)]);
            $this->item->set($array);
        }

        return $this;
    }

    public function all(): array
    {
        return $this->item->get() ?: [];
    }

    public function clear(): static
    {
        $this->item->set([]);

        return $this;
    }

    public function save(): void
    {
        $this->cache->getAdapter()->save($this->item);
    }
}