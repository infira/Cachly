<?php

namespace Infira\Cachly;

class KeyStorage
{
    private $driverName;
    private $storageName;

    /**
     * @var DriverHelper
     */
    protected $Driver;

    public function __construct($Driver, string $mainInstance, string $storageName, string $driverName)
    {
        $this->driverName = $driverName;
        $this->storageName = $mainInstance . '-' . $storageName;
        $this->Driver = &$Driver;
    }

    /**
     * Register key and CID pair
     *
     * @param string $CID
     * @param string $key
     */
    public function register(string $CID, string $key)
    {
        $keys = $this->getList();
        if (!array_key_exists($CID, $keys)) //do not trigger keys update twice
        {
            $keys[$CID] = $key;
            $this->save($keys);
        }
    }

    /**
     * Remove key from storage
     *
     * @param string $CID
     */
    public function remove(string $CID)
    {
        //Remove CID from keys
        $keys = $this->getList();
        unset($keys[$CID]);
        $this->save($keys);
    }

    /**
     * Set instance/collection cacheKey/cacheID pairs
     *
     * @param array $keys
     * @return bool
     */
    private function save(array $keys): bool
    {
        return $this->Driver->set($this->genCID($this->storageName), $keys);
    }

    /**
     * Get instance/collection cacheKey/cacheID pairs
     *
     * @return array
     */
    public function getList(): array
    {
        $CID = $this->genCID($this->storageName);
        $keys = $this->Driver->exists($CID) ? $this->Driver->get($CID) : [];
        $keys = is_object($keys) ? (array)$keys : (is_array($keys) ? $keys : []);

        return $keys;
    }

    /**
     * Delete current instance/collection cacheKey/cacheID pairs
     *
     * @return bool
     */
    public function delete(): bool
    {
        return $this->Driver->delete($this->genCID($this->storageName));
    }


    /**
     * Generate cache ID for keys
     *
     * @param string $key
     * @return string
     */
    private function genCID(string $key): string
    {
        if (!$key) {
            Cachly::error("Cache key cannot be empty");
        }

        return Cachly::hash(Cachly::genHashKey($this->driverName, $this->storageName, $key));
    }
}