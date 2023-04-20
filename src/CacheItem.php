<?php

namespace Infira\Cachly;

use DateInterval;
use DateTime;
use DateTimeInterface;
use Exception;
use Infira\Cachly\Support\Helpers;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\CacheItem as BaseCacheItem;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @template TValue
 */
class CacheItem implements ItemInterface, \ArrayAccess
{
    private bool $autoSave = false;
    private bool $isDirty = false;
    private int|string|DateTimeInterface|DateInterval|null $expiry = null;

    public function __construct(
        private readonly CacheInstance $cache,
        private BaseCacheItem $baseItem,
        mixed $value = null
    ) {
        if (func_num_args() === 3) {
            $this->set($value);
        }
    }

    public function baseItem(): BaseCacheItem
    {
        return $this->baseItem;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function autoSave(bool $onOff): static
    {
        $this->autoSave = $onOff;
        if ($onOff) {
            $this->save();
        }

        return $this;
    }

    public function isAutoSave(): bool
    {
        return $this->autoSave;
    }

    /**
     * Does item have changed in any war (value, expires, tags)
     * Note: item can be saved but changes is not saved
     *
     * @return bool
     */
    public function isDirty(): bool
    {
        return $this->isDirty;
    }

    /**
     * Persists a cache item only when item is dirty immediately.
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    public function save(): bool
    {
        if ($this->isDirty) {
            return $this->commit();
        }

        return false;
    }

    /**
     * Persists a cache item immediately.
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    public function commit(): bool
    {
        $saved = $this->cache->save($this->baseItem);
        $this->baseItem = $this->cache->getAdapter()->getItem($this->getKey());
        $this->isDirty = false;

        return $saved;
    }

    /**
     * Sets a cache item to be persisted later.
     *
     * @return CacheItem
     */
    public function defer(): static
    {
        $this->markDirty(true);
        $this->cache->saveDeferred($this->baseItem);

        return $this;
    }

    /**
     * @example 0|null - never expires
     * @example "+10 hours" -  will be converted to time using \new DateTime($expires) using forwarded call CacheItem::expiresAt()
     * @example 10 -  TTL(time to live) forwarded call CacheItem::expiresAfter()
     * @param  int|string|DateTimeInterface|DateInterval|null  $expires
     * @return CacheItem
     * @throws Exception
     */
    public function expires(int|string|DateTimeInterface|DateInterval|null $expires = null): static
    {
        if (empty($expires)) {
            return $this->expiresAt(null);
        }
        if ($expires instanceof DateInterval || is_int($expires)) {
            return $this->expiresAfter($expires);
        }

        if ($expires instanceof DateTimeInterface) {
            return $this->expiresAt($expires);
        }

        return $this->expiresAt(new DateTime($expires));
    }

    /**
     * @template TArguments
     * @param  class-string<TValue,TArguments>  $class
     * @param  mixed  ...$arguments
     * @return mixed
     */
    public function pipeInto(string $class, mixed ...$arguments): mixed
    {
        return new $class($this->get(), ...$arguments);
    }


    /**
     * Transform current item using callback
     *
     * @param  callable(CacheItem): void  $callback
     * @return static
     */
    public function transform(callable $callback): static
    {
        $callback($this);

        return $this;
    }

    //region proxies

    /** @inheritDoc */
    public function getKey(): string
    {
        return $this->baseItem->getKey();
    }

    /** @inheritDoc */
    public function get(): mixed
    {
        return $this->baseItem->get();
    }

    /**
     * Does item have changed in any way or is not hit (https://symfony.com/doc/current/components/cache/cache_items.html#cache-item-hits-and-misses)
     *
     * @return bool
     */
    public function isHit(): bool
    {
        if ($this->isDirty) {
            return false;
        }

        return $this->baseItem->isHit();
    }

    /**
     * When callable is passed then uses that to set new value
     *
     * @param  (callable(TValue): TValue)|mixed  $value
     * @return $this
     * @throws InvalidArgumentException
     */
    public function set(mixed $value): static
    {
        if (Helpers::isCallable($value)) {
            $value = $value($this->get());
        }
        $this->markDirty($value !== $this->get());
        $this->baseItem->set($value);
        $this->doAuto();

        return $this;
    }

    /**
     * Acts ass $array[] = $value
     *
     * @param  mixed  $value
     * @return $this
     * @throws InvalidArgumentException
     */
    public function add(mixed $value): static
    {
        $arr = $this->get();
        $arr[] = $value;

        return $this->set($arr);
    }

    public function push(mixed ...$values): static
    {
        $arr = $this->get();
        foreach ($values as $value) {
            $arr[] = $value;
        }

        return $this->set($arr);
    }

    /** @inheritDoc */
    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->markDirty($expiration !== $this->expiry);
        $this->baseItem->expiresAt($expiration);
        $this->expiry = $expiration;
        $this->doAuto();

        return $this;
    }

    /** @inheritDoc */
    public function expiresAfter(DateInterval|int|null $time): static
    {
        $this->markDirty($time !== $this->expiry);
        $this->baseItem->expiresAfter($time);
        $this->expiry = $time;
        $this->doAuto();

        return $this;
    }

    /** @inheritDoc */
    public function tag(iterable|string $tags): static
    {
        $this->markDirty(true);
        $this->baseItem->tag($tags);
        $this->doAuto();

        return $this;
    }

    /** @inheritDoc */
    public function getMetadata(): array
    {
        return $this->baseItem->getMetadata();
    }

    //endregion

    /**
     * @throws InvalidArgumentException
     */
    protected function doAuto(): void
    {
        if ($this->autoSave) {
            $this->save();
        }
    }

    protected function markDirty(bool $isDirty): static
    {
        $this->isDirty = $isDirty;

        return $this;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->get()[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get()[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $current = $this->get();
        if ($offset === null) {
            $current[] = $value;
        }
        else {
            $current[$offset] = $value;
        }
        $this->set($current);
    }

    public function offsetUnset(mixed $offset): void
    {
        $current = $this->get();
        unset($current[$offset]);
        $this->set($current);
    }
}