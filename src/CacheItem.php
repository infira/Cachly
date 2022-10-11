<?php

namespace Infira\Cachly;

class CacheItem
{
    private mixed $value;//real value
    private int $expires;//expires

    public function __construct(mixed $value, int $expires)
    {
        $this->value = $value;
        $this->expires = $expires;
    }

    public function neverExpires(): bool
    {
        return $this->expires === 0;
    }

    public function isExpired(): bool
    {
        if ($this->expires === 0) {
            return false;
        }

        return $this->expires > 0 && time() > $this->expires;
    }

    public function getExpires(): int
    {
        return $this->expires;
    }
}