<?php

namespace Infira\Cachly;

use Infira\Utils\Regex;
use stdClass;
use Infira\Utils\ClassFarm;

class Cacher
{
	private $collections = [];
	private $driverName;
	private $instanceName;
	private $key         = '';
	
	/**
	 * @var DriverHelper
	 */
	protected $Driver;
	
	/**
	 * @param string          $instanceName
	 * @param DriverHelper    $Driver
	 * @param                 $Driver
	 */
	public function __construct(string $instanceName, $Driver)
	{
		$this->driverName   = $Driver->getName();
		$this->instanceName = $instanceName;
		if ($Driver)
		{
			$this->Driver = &$Driver;
		}
	}
	
	/**
	 * @return KeyStorage
	 */
	private function Keys()
	{
		return ClassFarm::instance("Cachly->Driver->cachlyKeyHolder" . $this->instanceName . $this->driverName, function ()
		{
			return new KeyStorage($this->Driver, $this->instanceName, 'keys', $this->driverName);
		});
	}
	
	/**
	 * @return KeyStorage
	 */
	private function CollectionKeys()
	{
		return ClassFarm::instance("Cachly->Driver->cachlyCollectionKeyHolder" . $this->instanceName . $this->driverName, function ()
		{
			return new KeyStorage($this->Driver, $this->instanceName, 'ckeys', $this->driverName);
		});
	}
	
	/**
	 * Makes a cache collection
	 *
	 * @param string $key
	 * @return Cacher
	 */
	public function Collection(string $key): Cacher
	{
		if (!isset($this->collections[$key]))
		{
			$this->collections[$key] = $this->createCollectionDriver($key, true);
		}
		
		return $this->collections[$key];
	}
	
	/**
	 * Call $callback for every collection<br />$callback($Colleciton,$collectionName)
	 *
	 * @param callable $callback
	 * @return void
	 */
	public function eachCollection(callable $callback): void
	{
		foreach ($this->getCollections() as $name => $Collection)
		{
			call_user_func_array($callback, [$Collection, $name]);
		}
	}
	
	/**
	 * Get all current collections
	 *
	 * @return array
	 */
	public function getCollections(): array
	{
		$output = [];
		foreach ($this->CollectionKeys()->getList() as $CID => $key)
		{
			$output[$key] = $this->createCollectionDriver($key, false);
		}
		
		return $output;
	}
	
	/**
	 * Generate driver for collection instance
	 *
	 * @param string $key
	 * @param bool   $registerKeyStorage
	 * @return Cacher
	 */
	private function createCollectionDriver(string $key, bool $registerKeyStorage = false): Cacher
	{
		$cKey   = $this->instanceName . "-collection-" . $key;
		$Driver = new self($cKey, $this->Driver);
		if ($registerKeyStorage)
		{
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
		foreach ($this->Keys()->getList() as $CID => $key)
		{
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
		foreach ($this->getItems() as $name => $value)
		{
			call_user_func_array($callback, [$value, $name]);
		}
	}
	
	/**
	 * Set cache value
	 *
	 * @param string     $key     -  cache key, if is empty then try to use key setted by key() method
	 * @param mixed      $value   - value to store
	 * @param int|string $expires - when expires. (int)0 - forever,(string)"10 hours" -  will be converted to time using strtotime(), (int)1596885301 - will tell Driver when to expire. If $expires is in the past, it will be converted as forever
	 * @return string - returns cacheID which was used to save $value
	 */
	public function set(string $key = '', $value, $expires = 0): string
	{
		if ($key === '')
		{
			$key = $this->key;
		}
		$CID = $this->genItemCID($key);
		$this->setByCID($CID, $value, $expires);
		$this->Keys()->register($CID, $key);
		
		return $CID;
	}
	
	/**
	 * @param string     $CID
	 * @param mixed      $value
	 * @param int|string $expires - when expires. (int)0 - forever,(string)"10 hours" -  will be converted to time using strtotime(), (int)1596885301 - will tell Driver when to expire. If $expires is in the past, it will be converted as forever
	 * @return string
	 */
	private function setByCID(string $CID, $value, $expires = 0)
	{
		/*
		 * Make sure that Driver get always integer
		 */
		if (!empty($expires))
		{
			if ($expires === "0")
			{
				$expires = 0;
			}
			elseif (is_string($expires))
			{
				if ($expires{0} != "+")
				{
					$expires = "+$expires";
				}
				$expires = strtotime($expires);
			}
			elseif (is_numeric($expires))
			{
				$expires = intval($expires);
			}
		}
		else
		{
			$expires = 0;
		}
		
		$Node    = new stdClass();
		$Node->v = $value;    //value
		$Node->t = $expires;  //timestamp
		$this->Driver->set($CID, $Node, $expires);
		
		return $CID;
	}
	
	/**
	 * Get cache item
	 *
	 * @param string $key               - cache key, if is empty then try to use key setted by key() method
	 * @param mixed  $returnOnNotExists - return that when item is not found
	 * @return mixed
	 */
	public function get(string $key = '', $returnOnNotExists = null)
	{
		if ($key === '')
		{
			$key = $this->key;
		}
		
		return $this->getByCID($this->genItemCID($key), $returnOnNotExists);
	}
	
	/**
	 * Get cache item by cacheID
	 *
	 * @param string $CID
	 * @param mixed  $returnOnNotExists
	 * @return mixed
	 */
	private function getByCID(string $CID, $returnOnNotExists = null)
	{
		if (!$this->existsByCID($CID))
		{
			return $returnOnNotExists;
		}
		
		return $this->Driver->get($CID)->v;
	}
	
	/**
	 * Get multiple items by keys
	 *
	 * @param array $keys - for examples ['key1','key2]
	 * @return array - ['key1'=>'value1', 'key2'=>'value']
	 */
	public function getMulti(array $keys): array
	{
		$output = [];
		foreach ($keys as $key)
		{
			$output[$key] = $this->get($key);
		}
		
		return $output;
	}
	
	/**
	 * Does cache item exists
	 *
	 * @param string $key - cache key, if is empty then try to use key setted by key() method
	 * @return bool
	 */
	public function exists(string $key = ''): bool
	{
		if ($key === '')
		{
			$key = $this->key;
		}
		
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
		if (!$this->Driver->exists($CID))
		{
			return false;
		}
		$Node = $this->Driver->get($CID);
		if (!is_object($Node))
		{
			return false;
		}
		if ($Node->t == 0)
		{
			return true;
		}
		if ($Node->t > 0 && time() > $Node->t)
		{
			$this->deleteByCID($CID);
			
			return false;
		}
		
		return true;
	}
	
	/**
	 * Get cache items by regular expression
	 *
	 * @param string $pattern
	 * @return array
	 */
	public function getRegex(string $pattern): array
	{
		$pattern   = Regex::fix($pattern);
		$res       = [];
		$filtererd = preg_grep($pattern, $this->Keys()->getList());
		foreach ($filtererd as $CID => $cacheKey)
		{
			if ($this->existsByCID($CID))
			{
				$res[$cacheKey] = $this->getByCID($CID);
			}
		}
		
		return $res;
	}
	
	/**
	 * Is cache item expired
	 *
	 * @param string $key - cache key, if is empty then try to use key setted by key() method
	 * @return bool
	 */
	public function isExpired(string $key = ''): bool
	{
		if ($key === '')
		{
			$key = $this->key;
		}
		
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
		if (!$this->Driver->exists($CID))
		{
			return true;
		}
		$r = $this->expiresAtByCID($CID);
		if ($r === "expired")
		{
			return true;
		}
		elseif ($r === "never")
		{
			return false;
		}
		elseif (time() > $r)
		{
			return true;
		}
		
		return false;
	}
	
	/**
	 * Tells when cache item expires
	 *
	 * @param string $key - cache key, if is empty then try to use key setted by key() method
	 * @return string|null|integer - "expired","never" or timestamp when will be expired, returns null when not exists
	 */
	public function expiresAt(string $key = '')
	{
		if ($key === '')
		{
			$key = $this->key;
		}
		
		return $this->expiresAtByCID($this->genItemCID($key));
	}
	
	/**
	 * Tells when cache item expires
	 *
	 * @param string $CID
	 * @return string|null|integer - "expired","never" or timestamp when will be expired, returns null when not exists
	 */
	private function expiresAtByCID(string $CID)
	{
		if (!$this->Driver->exists($CID))
		{
			return null;
		}
		$r = $this->Driver->get($CID);
		if ($r === null)
		{
			return "expired";
		}
		if ($r->t == 0)
		{
			return "never";
		}
		
		return $r->t;
	}
	
	/**
	 * Delete cache item
	 *
	 * @param string $key
	 * @return bool
	 */
	public function delete(string $key = ''): bool
	{
		if ($key === '')
		{
			$key = $this->key;
		}
		
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
		$pattern   = Regex::fix($pattern);
		$filtererd = preg_grep($pattern, $this->Keys()->getList());
		foreach ($filtererd as $CID => $key)
		{
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
		foreach ($this->Keys()->getList() as $CID => $key)
		{
			if ($this->isExpiredByCID($CID))
			{
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
		foreach ($this->Keys()->getList() as $CID => $key)
		{
			$this->Driver->delete($CID);
		}
		$this->Keys()->delete();
		
		return true;
	}
	
	/**
	 * Get current driver
	 *
	 * @return DriverHelper
	 */
	public function getDriver()
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
	 * @param mixed            $key           - cache key, if is empty then try to use key setted by key() method
	 * @param string|array|int $callback      method result will be setted to memory for later use
	 * @param mixed            $callbackArg1  - this will pass to $callback as argument1
	 * @param mixed            $callbackArg2  - this will pass to $callback as argument2
	 * @param mixed            $callbackArg3  - this will pass to $callback as argument3
	 * @param mixed            $callbackArg_n - ....
	 * @return mixed - $callback result
	 */
	public function once($key, callable $callback)
	{
		if ($key === '')
		{
			$key = $this->key;
		}
		if (is_array($key))
		{
			$key = $this->packKey($key);
		}
		$CID = $this->genItemCID($key);
		if (!$this->existsByCID($CID))
		{
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
	 * @param callable         $callback
	 * @param bool             $forceSet      - if its true then $callback will be called regardless of is the $key setted or not
	 * @param mixed            $callbackArg1  - this will pass to $callback as argument1
	 * @param mixed            $callbackArg2  - this will pass to $callback as argument2
	 * @param mixed            $callbackArg3  - this will pass to $callback as argument3
	 * @param mixed            $callbackArg_n - ....
	 * @return mixed|null - $callback result
	 */
	public function onceForce($key, callable $callback, bool $forceSet = false)
	{
		if ($key === '')
		{
			$key = $this->key;
		}
		if (is_array($key))
		{
			$key = $this->packKey($key);
		}
		$CID = $this->genItemCID($key);
		if (!$this->existsByCID($CID) or $forceSet == true)
		{
			$this->Keys()->register($CID, $key);
			$this->setByCID($CID, call_user_func_array($callback, array_slice(func_get_args(), 3)));
		}
		
		return $this->getByCID($CID);
	}
	
	/**
	 * Call $callback once per $key existence or when its expired
	 * All arguments after  $forceSet will be passed to callable method
	 *
	 * @param string|array|int $key           - cache key, if is empty then try to use key setted by key() method
	 * @param callable         $callback
	 * @param int|string       $expires       - when expires. (int)0 - forever,(string)"10 hours" -  will be converted to time using strtotime(), (int)1596885301 - will tell Driver when to expire. If $expires is in the past, it will be converted as forever. When expired $callback will be called again
	 * @param mixed            $callbackArg1  - this will pass to $callback as argument1
	 * @param mixed            $callbackArg2  - this will pass to $callback as argument2
	 * @param mixed            $callbackArg3  - this will pass to $callback as argument3
	 * @param mixed            $callbackArg_n - ....
	 * @return mixed|null - $callback result
	 */
	public function onceExpire($key, callable $callback, $expires = 0)
	{
		if ($key === '')
		{
			$key = $this->key;
		}
		if (is_array($key))
		{
			$key = $this->packKey($key);
		}
		$CID = $this->genItemCID($key);
		if (!$this->existsByCID($CID))
		{
			$this->Keys()->register($CID, $key);
			$this->setByCID($CID, call_user_func_array($callback, array_slice(func_get_args(), 3)), $expires);
		}
		
		return $this->getByCID($CID);
	}
	
	/**
	 * Set key for further use
	 *
	 * @param string|array|int $key
	 * @return $this
	 */
	public function key($key)
	{
		if (is_array($key))
		{
			$key = $this->packKey($key);
		}
		$this->key = $key;
		
		return $this;
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
	 * @param string $key
	 * @return string
	 */
	private function genItemCID(string $key)
	{
		if (!$key)
		{
			Cachly::error("Cache key cannot be empty");
		}
		$hashKey = $_SERVER['HTTP_HOST'] . ";" . $_SERVER['DOCUMENT_ROOT'] . ";" . $this->driverName . $this->instanceName . $key;
		
		return Cachly::hash($hashKey);
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
		foreach ($key as $k => $v)
		{
			if (is_bool($v))
			{
				$v = ($v) ? '1' : '0';
			}
			elseif (is_array($v))
			{
				$v = $this->packKey($v);
			}
			$items[] = $v;
		}
		
		return join(',', $items);
	}
}

?>