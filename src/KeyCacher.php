<?php

namespace Infira\Cachly;

class KeyCacher
{
    private Cacher $cacher;
    private string $key = '';

    protected DriverHelper $Driver;

    /**
     * @throws CachlyException
     */
    public function __construct(Cacher $cacher, string $key)
    {
        if (!$key) {
            Cachly::error("Cache key cannot be empty");
        }
        $this->cacher = &$cacher;
    }

    /**
     * Set cache value
     *
     * @param mixed $value - value to store
     * @param int|string $expires - when expires. (int)0 - forever,(string)"10 hours" -  will be converted to time using strtotime(), (int)1596885301 - will tell Driver when to expire. If $expires is in the past, it will be converted as forever
     * @return string - returns cacheID which was used to save $value
     * @throws CachlyException
     */
    public function set(mixed $value, int|string $expires = 0): string
    {
        return $this->cacher->set($this->key, $value, $expires);
    }

    /**
     * Get cache item
     *
     * @param mixed $default - return that when item is not found
     * @return mixed
     * @throws CachlyException
     */
    public function get(mixed $default = null): mixed
    {
        return $this->cacher->get($this->key, $default);
    }

    /**
     * Does cache item exists
     *
     * @return bool
     * @throws CachlyException
     */
    public function exists(): bool
    {
        return $this->cacher->exists($this->key);
    }

    /**
     * @throws CachlyException
     */
    public function isExpired(): bool
    {
        return $this->cacher->isExpired($this->key);
    }

    /**
     * Tells when cache item expires
     *
     * @return string|null|integer - "expired","never" or timestamp when will be expired, returns null when not exists
     * @throws CachlyException
     */
    public function expiresAt(): int|string|null
    {
        return $this->cacher->expiresAt($this->key);
    }

    /**
     * Delete cache item
     *
     * @return bool
     * @throws CachlyException
     */
    public function delete(): bool
    {
        return $this->cacher->delete($this->key);
    }
}