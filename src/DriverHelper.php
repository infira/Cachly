<?php

namespace Infira\Cachly;

abstract class DriverHelper
{
    private $driverName;
    protected $checkConfiguredViaOpt;
    protected $fallbackDriverName;

    /**
     * @var $this
     */
    protected $FallbackDriver;

    public function __construct()
    {
        if (!$this->driverName) {
            Cachly::error('Driver name is not set, use $this->setDriver');
        }
        if ($this->fallbackDriverName === $this->driverName) {
            Cachly::error('Fallback driver cannot be the same to self');
        }
    }

    protected function setDriver(string $driver): void
    {
        $this->driverName = $driver;
    }

    protected function fallbackORShowError(string $error): void
    {
        if ($this->fallbackDriverName) {
            $this->FallbackDriver = Cachly::$Driver->get($this->fallbackDriverName);
        }
        else {
            Cachly::error($error);
        }
    }

    protected function setFallbackDriver(string $driverName): void
    {
        $this->fallbackDriverName = $driverName;
    }

    /**
     * Get a current driver name
     *
     * @return string
     */
    final public function getName(): string
    {
        return $this->driverName;
    }

    /**
     * Get client
     *
     * @return null| object - is driver does not have client it returns null
     */
    public function getClient()
    {
        return null;
    }

    /**
     * @return bool
     */
    public static function isConfigured(): bool
    {
        return true;
    }

    /**
     * Save cache data
     *
     * @param string $CID
     * @param mixed $data
     * @param int $expires
     * @return bool
     * @throws CachlyException
     */
    final public function set(string $CID, mixed $data, int $expires = 0): bool
    {
        if ($this->FallbackDriver) {
            $this->FallbackDriver->set($CID, $data, $expires);
        }
        else {
            $this->doSet($CID, $data, $expires);
        }

        return true;
    }

    /**
     * Does cache item exists
     *
     * @param string $CID
     * @return bool
     */
    final public function exists(string $CID): bool
    {
        if ($this->FallbackDriver) {
            return $this->FallbackDriver->exists($CID);
        }

        return $this->doExists($CID);
    }

    /**
     * Get cache item
     *
     * @param string $CID
     * @return mixed
     */
    final public function get(string $CID): mixed
    {
        if ($this->FallbackDriver) {
            return $this->FallbackDriver->get($CID);
        }

        return $this->doGet($CID);
    }

    /**
     * Delete cache item
     *
     * @param string $CID
     * @return bool
     */
    final public function delete(string $CID): bool
    {
        if ($this->FallbackDriver) {
            return $this->FallbackDriver->delete($CID);
        }

        return $this->doDelete($CID);
    }

    /**
     * Get cache items
     *
     * @return array
     */
    final public function getItems(): array
    {
        if ($this->FallbackDriver) {
            return $this->FallbackDriver->getItems();
        }

        return $this->doGetItems();
    }

    /**
     * debug cache items
     */
    final public function debug(): void
    {
        debug($this->getItems());
    }

    /**
     * Flush driver
     *
     * @return bool
     */
    final public function flush(): bool
    {
        if ($this->FallbackDriver) {
            return $this->FallbackDriver->flush();
        }

        return $this->doFlush();
    }

    /**
     * Garbage collector
     *
     * @return bool
     */
    final public function gc(): bool
    {
        if ($this->FallbackDriver) {
            return $this->FallbackDriver->gc();
        }

        return $this->doGc();
    }



    ######################### Abstractions

    /**
     * Set data to via cache driver
     *
     * @param string $CID - cache ID to be saved
     * @param mixed $data - data to be saved
     * @param int $expires - when it expires as timestamp. Its always in the future or 0
     * @return mixed
     */
    abstract protected function doSet(string $CID, mixed $data, int $expires = 0): bool;

    /**
     * Does cacheID exists
     *
     * @param string $CID - cache ID
     * @return bool
     */
    abstract protected function doExists(string $CID): bool;

    /**
     * Retrieve cache node
     *
     * @param string $CID - cache ID
     * @return mixed
     */
    abstract protected function doGet(string $CID): mixed;

    /**
     * Delete cache node
     *
     * @param string $CID - cache ID
     * @return bool
     */
    abstract protected function doDelete(string $CID): bool;

    /**
     * Get all items
     *
     * @return array
     */
    abstract protected function doGetItems(): array;

    /**
     * Flush all items
     *
     * @return bool
     */
    abstract protected function doFlush(): bool;

    /**
     * Performs a garbage collections (deletes expired)
     *
     * @return bool
     */
    abstract protected function doGc(): bool;
}

?>