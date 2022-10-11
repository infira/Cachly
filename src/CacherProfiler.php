<?php

namespace Infira\Cachly;

class CacherProfiler extends Cacher
{
    /**
     * @inheritDoc
     */
    public function Collection(string $key): Cacher
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $value, $expires = 0): string
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    private function setByCID(string $CID, $value, $ttl = 0)
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function get(string $key = '', $default = null)
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    private function getByCID(string $CID, $returnOnNotExists = null)
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getMulti(array $keys): array
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key = ''): bool
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function existsByCID(string $CID): bool
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getRegex(string $pattern): array
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function isExpired(string $key = ''): bool
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function expiresAt(string $key = '')
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key = ''): bool
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function deleteRegex(string $pattern): bool
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function deletedExpired(): bool
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function flush(): bool
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getItems(): array
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function once($key, callable $callback)
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function onceForce($key, callable $callback, bool $forceSet = false)
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function onceExpire($key, callable $callback, $expires = 0)
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getInstancesKeys(): array
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getKeys(string $instanceKey = null): array
    {
        return $this->profileIt(__FUNCTION__, func_get_args());
    }

    private function profileIt($methodName, $arguments)
    {
        Prof("cachly")->startTimer($methodName);
        $output = parent::$methodName(...$arguments);
        Prof("cachly")->stopTimer($methodName);

        return $output;
    }

}

?>