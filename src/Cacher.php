<?php

namespace Infira\Cachly;

use stdClass;
use Wolo\ClassFarm\ClassFarm;

class Cacher
{
    private array $collections = [];
    private string $driverName;
    private string $instanceName;

    protected DriverHelper $Driver;

    /**
     * @param string $instanceName
     * @param DriverHelper $Driver
     */
    public function __construct(string $instanceName, DriverHelper $Driver)
    {
        $this->driverName = $Driver->getName();
        $this->instanceName = $instanceName;
        $this->Driver = &$Driver;
    }

    private function Keys(): KeyStorage
    {
        return ClassFarm::instance("Cachly->Driver->cachlyKeyHolder" . $this->instanceName . $this->driverName, function () {
            return new KeyStorage($this->Driver, $this->instanceName, 'keys', $this->driverName);
        });
    }

    private function CollectionKeys(): KeyStorage
    {
        return ClassFarm::instance("Cachly->Driver->cachlyCollectionKeyHolder" . $this->instanceName . $this->driverName, function () {
            return new KeyStorage($this->Driver, $this->instanceName, 'ckeys', $this->driverName);
        });
    }

    /**
     * Makes a cache collection
     *
     * @param string|int $key
     * @return Cacher
     * @throws CachlyException
     */
    public function Collection(string|int $key): static
    {
        if (!isset($this->collections[$key])) {
            $this->collections[$key] = $this->createCollectionDriver($key, true);
        }

        return $this->collections[$key];
    }

    /**
     * Call $callback for every collection<br />$callback($Colleciton,$collectionName)
     *
     * @param callable $callback
     * @return void
     * @throws CachlyException
     */
    public function eachCollection(callable $callback): void
    {
        foreach ($this->getCollections() as $name => $Collection) {
            $callback($Collection, $name);
        }
    }

    /**
     * Get all current collections
     *
     * @return array
     * @return Cacher[]
     * @throws CachlyException
     */
    public function getCollections(): array
    {
        $output = [];
        foreach ($this->CollectionKeys()->getList() as $key) {
            $output[$key] = $this->createCollectionDriver($key, false);
        }

        return $output;
    }

    /**
     * Generate driver for collection instance
     *
     * @param string|int $key
     * @param bool $registerKeyStorage
     * @return static
     * @throws CachlyException
     */
    private function createCollectionDriver(string|int $key, bool $registerKeyStorage = false): static
    {
        $cKey = $this->instanceName . "-collection-" . $key;
        $Driver = new self($cKey, $this->Driver);
        if ($registerKeyStorage) {
            $this->CollectionKeys()->register($this->genItemCID($cKey), $key);
        }

        return $Driver;
    }

    /**
     * Get all current instance/collection or collection cache items
     *
     * @return array
     */
    public function getItems(): array
    {
        $output = [];
        foreach ($this->Keys()->getList() as $CID => $key) {
            $output[$key] = $this->getByCID($CID);
        }

        return $output;
    }

    /**
     * Loops all items and and call $callback for every item<br />$callback($value,$cacheKey)
     *
     * @param callable $callback
     * @return void
     */
    public function each(callable $callback): void
    {
        foreach ($this->getItems() as $name => $value) {
            $callback($value, $name);
        }
    }

    /**
     * Set cache value
     *
     * @param string|int $key
     * @param mixed $value - value to store
     * @param int|string $expires - when expires. (int)0 - forever,(string)"10 hours" -  will be converted to time using strtotime(), (int)1596885301 - will tell Driver when to expire. If $expires is in the past, it will be converted as forever
     * @return string - returns cacheID which was used to save $value
     * @throws CachlyException
     */
    public function set(string|int $key, mixed $value, int|string $expires = 0): string
    {
        $CID = $this->genItemCID($key);
        $this->setByCID($CID, $value, $expires);
        $this->Keys()->register($CID, $key);

        return $CID;
    }

    /**
     * @param string $CID
     * @param mixed $value
     * @param int|string $expires - when expires. (int)0 - forever,(string)"10 hours" -  will be converted to time using strtotime(), (int)1596885301 - will tell Driver when to expire. If $expires is in the past, it will be converted as forever
     * @return void
     * @throws CachlyException
     */
    private function setByCID(string $CID, mixed $value, int|string $expires = 0): void
    {
        $expiresIn = 0;
        /*
         * Make sure that Driver get always integer
         */
        if (is_numeric($expires) && !empty($expires)) {
            $expiresIn = (int)$expires;
        }
        elseif (is_string($expires) && !empty($expires)) {
            if ($expires[0] !== "+") {
                $expires = "+$expires";
            }
            $expiresIn = strtotime($expires);
        }

        $Node = new stdClass();
        $Node->v = $value;    //value
        $Node->t = $expiresIn;  //timestamp
        $this->Driver->set($CID, $Node, $expiresIn);
    }

    /**
     * Get cache item
     *
     * @param string|int $key
     * @param mixed $default - return that when item is not found
     * @return mixed
     * @throws CachlyException
     */
    public function get(string|int $key, mixed $default = null): mixed
    {
        return $this->getByCID($this->genItemCID($key), $default);
    }

    /**
     * Get cache item by cacheID
     *
     * @param string $CID
     * @param mixed $default
     * @return mixed
     */
    private function getByCID(string $CID, mixed $default = null): mixed
    {
        if (!$this->existsByCID($CID)) {
            return $default;
        }

        return $this->Driver->get($CID)->v;
    }

    /**
     * Get multiple items by keys
     *
     * @param array $keys - for examples ['key1','key2]
     * @return array - ['key1'=>'value1', 'key2'=>'value']
     * @throws CachlyException
     */
    public function getMulti(array $keys): array
    {
        $output = [];
        foreach ($keys as $key) {
            $output[$key] = $this->get($key);
        }

        return $output;
    }

    /**
     * Does cache item exists
     *
     * @param string|int $key
     * @return bool
     * @throws CachlyException
     */
    public function exists(string|int $key): bool
    {
        return $this->existsByCID($this->genItemCID($key));
    }

    /**
     * Does cache item exists by cacheID
     *
     * @param string $CID
     * @return bool
     */
    private function existsByCID(string $CID): bool
    {
        if (!$this->Driver->exists($CID)) {
            return false;
        }

        return !$this->getItemByCID($CID)->isExpired();
    }

    /**
     * Get cache items by regular expression
     *
     * @param string $pattern
     * @return array
     */
    public function getRegex(string $pattern): array
    {
        $res = [];
        $filtered = preg_grep($pattern, $this->Keys()->getList());
        foreach ($filtered as $CID => $cacheKey) {
            if ($this->existsByCID($CID)) {
                $res[$cacheKey] = $this->getByCID($CID);
            }
        }

        return $res;
    }

    /**
     * Is cache item expired
     *
     * @param string|int $key
     * @return bool
     * @throws CachlyException
     */
    public function isExpired(string|int $key): bool
    {
        return $this->isExpiredByCID($this->genItemCID($key));
    }

    /**
     * Is cache item expired by cacheID
     *
     * @param string $CID
     * @return bool
     */
    private function isExpiredByCID(string $CID): bool
    {
        if (!$this->Driver->exists($CID)) {
            return true;
        }
        $r = $this->expiresAtByCID($CID);
        if ($r === "expired") {
            return true;
        }

        if ($r === "never") {
            return false;
        }

        if (time() > $r) {
            return true;
        }

        return false;
    }

    /**
     * Tells when cache item expires
     *
     * @param string|int $key
     * @return string|null|integer - "expired","never" or timestamp when will be expired, returns null when not exists
     * @throws CachlyException
     */
    public function expiresAt(string|int $key): int|string|null
    {
        return $this->expiresAtByCID($this->genItemCID($key));
    }

    /**
     * Tells when cache item expires
     *
     * @param string $CID
     * @return string|null|integer - "expired","never" or timestamp when will be expired, returns null when not exists
     */
    private function expiresAtByCID(string $CID): int|string|null
    {
        if (!$this->Driver->exists($CID)) {
            return "expired";
        }
        $item = $this->getItemByCID($CID);
        if ($item->neverExpires()) {
            return 'never';
        }
        if ($item->isExpired()) {
            return 'expired';
        }

        return $item->getExpires();
    }

    /**
     * Delete cache item
     *
     * @param string|int $key
     * @return bool
     * @throws CachlyException
     */
    public function delete(string|int $key): bool
    {
        return $this->deleteByCID($this->genItemCID($key));
    }

    /**
     * Delete cache item by cacheID
     *
     * @param string $CID
     * @return bool
     */
    private function deleteByCID(string $CID): bool
    {
        $this->Driver->delete($CID);
        $this->Keys()->remove($CID);

        return true;
    }

    /**
     * Delete by regular expression
     *
     * @param string $pattern
     * @return bool
     */
    public function deleteRegex(string $pattern): bool
    {
        $filtered = preg_grep($pattern, $this->Keys()->getList());
        foreach ($filtered as $CID => $key) {
            $this->deleteByCID($CID);
        }

        return true;
    }

    /**
     * Delete expired items from current instance/collection
     *
     * @return bool
     */
    public function deletedExpired(): bool
    {
        foreach ($this->Keys()->getList() as $CID => $key) {
            if ($this->isExpiredByCID($CID)) {
                $this->deleteByCID($CID);
            }
        }

        return true;
    }

    /**
     * Flush data on current instance/collection
     *
     * @return bool
     */
    public function flush(): bool
    {
        foreach ($this->Keys()->getList() as $CID => $key) {
            $this->Driver->delete($CID);
        }
        $this->Keys()->delete();

        return true;
    }

    public function getDriver(): DriverHelper
    {
        return $this->Driver;
    }

    /**
     * Dumps current instance/collection items
     *
     * @return void
     */
    public function debug(): void
    {
        debug($this->getItems());
    }

    /**
     * Call $callback once per $key existence
     * All arguments after  $callback will be passed to callable method
     *
     * @param string|int $key
     * @param string|array|int $callback method result will be setted to memory for later use
     * @param mixed $callbackArg1 - this will pass to $callback as argument1
     * @param mixed $callbackArg2 - this will pass to $callback as argument2
     * @param mixed $callbackArg3 - this will pass to $callback as argument3
     * @param mixed $callbackArg_n - ....
     * @return mixed - $callback result
     * @throws CachlyException
     */
    public function once($key, callable $callback): mixed
    {
        if (is_array($key)) {
            $key = $this->packKey($key);
        }
        $CID = $this->genItemCID($key);
        if (!$this->existsByCID($CID)) {
            $this->Keys()->register($CID, $key);
            $this->setByCID($CID, call_user_func_array($callback, array_slice(func_get_args(), 2)));
        }

        return $this->getByCID($CID);
    }

    /**
     * Call $callback once per $key existence or force it to call
     * All arguments after  $forceSet will be passed to callable method
     *
     * @param string|array|int $key
     * @param callable $callback
     * @param bool $forceSet - if its true then $callback will be called regardless of is the $key setted or not
     * @param mixed $callbackArg1 - this will pass to $callback as argument1
     * @param mixed $callbackArg2 - this will pass to $callback as argument2
     * @param mixed $callbackArg3 - this will pass to $callback as argument3
     * @param mixed $callbackArg_n - ....
     * @return mixed|null - $callback result
     * @throws CachlyException
     */
    public function onceForce($key, callable $callback, bool $forceSet = false): mixed
    {
        if (is_array($key)) {
            $key = $this->packKey($key);
        }
        $CID = $this->genItemCID($key);
        if (!$this->existsByCID($CID) || $forceSet) {
            $this->Keys()->register($CID, $key);
            $this->setByCID($CID, call_user_func_array($callback, array_slice(func_get_args(), 3)));
        }

        return $this->getByCID($CID);
    }

    /**
     * Call $callback once per $key existence or when its expired
     * All arguments after  $forceSet will be passed to callable method
     *
     * @param string|array|int $key - cache key, if is empty then try to use key setted by key() method
     * @param callable $callback
     * @param int|string $expires - when expires. (int)0 - forever,(string)"10 hours" -  will be converted to time using strtotime(), (int)1596885301 - will tell Driver when to expire. If $expires is in the past, it will be converted as forever. When expired $callback will be called again
     * @param mixed $callbackArg1 - this will pass to $callback as argument1
     * @param mixed $callbackArg2 - this will pass to $callback as argument2
     * @param mixed $callbackArg3 - this will pass to $callback as argument3
     * @param mixed $callbackArg_n - ....
     * @return mixed|null - $callback result
     * @throws CachlyException
     */
    public function onceExpire($key, callable $callback, $expires = 0): mixed
    {
        if (is_array($key)) {
            $key = $this->packKey($key);
        }
        $CID = $this->genItemCID($key);
        if (!$this->existsByCID($CID)) {
            $this->Keys()->register($CID, $key);
            $this->setByCID($CID, call_user_func_array($callback, array_slice(func_get_args(), 3)), $expires);
        }

        return $this->getByCID($CID);
    }

    /**
     * Set key for further use
     *
     * @param int|array|string $key
     * @return KeyCacher
     * @throws CachlyException
     */
    public function key(int|array|string ...$key): KeyCacher
    {
        return new KeyCacher($this, $this->packKey($key));
    }

    /**
     * Get instance/collection cacheKey/cacheID pairs
     *
     * @return array
     */
    public function getIDKeyPairs(): array
    {
        return $this->Keys()->getList();
    }

    /**
     * Get cache IDS
     *
     * @return array
     */
    public function getIDS(): array
    {
        return array_keys($this->Keys()->getList());
    }

    /**
     * Get cache keys
     *
     * @return array
     */
    public function getKeys(): array
    {
        return array_values($this->Keys()->getList());
    }

    /**
     * Generate cache ID for item
     *
     * @param string|int $key
     * @return string
     * @throws CachlyException
     */
    private function genItemCID(string|int $key): string
    {
        if (!$key) {
            Cachly::error("Cache key cannot be empty");
        }

        return Cachly::hash(Cachly::genHashKey($this->driverName, $this->instanceName, $key));
    }

    /**
     * Pack array key for key generation
     *
     * @param array $key
     * @return string
     */
    private function packKey(array $key): string
    {
        $items = [];
        foreach ($key as $v) {
            if (is_bool($v)) {
                $v = ($v) ? '1' : '0';
            }
            elseif (is_array($v) || is_object($v)) {
                $v = $this->packKey((array)$v);
            }
            $items[] = $v;
        }

        return implode(',', $items);
    }

    private function getItemByCID(string $CID): CacheItem
    {
        $node = $this->Driver->get($CID);

        return new CacheItem($node->v, $node->t);
    }
}