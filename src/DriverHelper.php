<?php

namespace Infira\Cachly;

use Infira\Cachly\Cachly;

abstract class DriverHelper
{
	private   $driverName;
	protected $checkConfiguredViaOpt;
	protected $fallbackDriverName;
	
	/**
	 * @var $this
	 */
	protected $FallbackDriver;
	
	public function __construct()
	{
		if (!$this->driverName)
		{
			Cachly::error('Driver name is not set, use $this->setDriver');
		}
		if ($this->fallbackDriverName === $this->driverName)
		{
			Cachly::error('Fallback driver cannot be the same to self');
		}
	}
	
	protected function setDriver(string $driver)
	{
		$this->driverName = $driver;
	}
	
	protected function fallbackORShowError(string $error)
	{
		if ($this->fallbackDriverName)
		{
			$this->FallbackDriver = Cachly::$Driver->make($this->fallbackDriverName);
		}
		else
		{
			Cachly::error($error);
		}
	}
	
	protected function setFallbackDriver()
	{
		$this->FallbackDriver = Cachly::$Driver->make($this->fallbackDriverName);
	}
	
	/**
	 * Get a current driver name
	 *
	 * @return string
	 */
	public final function getName(): string
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
	 * Check is the $data serializsable
	 *
	 * @param $value
	 * @return bool
	 */
	private function isSerializable($value)
	{
		if (is_closure($value) or is_resource($value))
		{
			return false;
		}
		if (is_string($value) or is_numeric($value))
		{
			return true;
		}
		$return = true;
		$arr    = [$value];
		
		array_walk_recursive($arr, function ($element) use (&$return)
		{
			if (is_object($element) && get_class($element) == 'Closure')
			{
				$return = false;
			}
		});
		
		return $return;
	}
	
	/**
	 * Save cache data
	 *
	 * @param string $CID
	 * @param mixed  $data
	 * @param int    $expires
	 * @return bool
	 */
	public final function set(string $CID, $data, int $expires = 0): bool
	{
		if (!$this->isSerializable($data))
		{
			Cachly::error('Cannot serialise cache data', ['data' => $data]);
		}
		if ($this->FallbackDriver)
		{
			$this->FallbackDriver->set($CID, $data, $expires);
		}
		else
		{
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
	public final function exists(string $CID): bool
	{
		if ($this->FallbackDriver)
		{
			return $this->FallbackDriver->exists($CID);
		}
		else
		{
			return $this->doExists($CID);
		}
	}
	
	/**
	 * Get cache item
	 *
	 * @param string $CID
	 * @return string
	 */
	public final function get(string $CID)
	{
		if ($this->FallbackDriver)
		{
			return $this->FallbackDriver->get($CID);
		}
		else
		{
			return $this->doGet($CID);
		}
	}
	
	/**
	 * Delete cache item
	 *
	 * @param string $CID
	 * @return bool
	 */
	public final function delete(string $CID): bool
	{
		if ($this->FallbackDriver)
		{
			return $this->FallbackDriver->delete($CID);
		}
		else
		{
			return $this->doDelete($CID);
		}
	}
	
	/**
	 * Get cache items
	 *
	 * @return array
	 */
	public final function getItems(): array
	{
		if ($this->FallbackDriver)
		{
			return $this->FallbackDriver->getItems();
		}
		else
		{
			return $this->doGetItems();
		}
	}
	
	/**
	 * debug cache items
	 */
	public final function debug()
	{
		debug($this->getItems());
	}
	
	/**
	 * Flush driver
	 *
	 * @return bool
	 */
	public final function flush(): bool
	{
		if ($this->FallbackDriver)
		{
			return $this->FallbackDriver->flush();
		}
		else
		{
			return $this->doFlush();
		}
	}
	
	/**
	 * Garbage collector
	 *
	 * @return bool
	 */
	public final function gc(): bool
	{
		if ($this->FallbackDriver)
		{
			return $this->FallbackDriver->gc();
		}
		else
		{
			return $this->doGc();
		}
	}
	
	
	
	######################### Abstractions
	
	/**
	 * Set data to via cache driver
	 *
	 * @param string $CID     - cache ID to be saved
	 * @param mixed  $data    - data to be saved
	 * @param int    $expires - when it expires as timestamp. Its always in the future or 0
	 * @return mixed
	 */
	abstract protected function doSet(string $CID, $data, int $expires = 0): bool;
	
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
	 * @return string
	 */
	abstract protected function doGet(string $CID);
	
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