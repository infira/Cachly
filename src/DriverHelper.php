<?php

namespace Infira\Cachly;

use Infira\Cachly\Cachly;

abstract class DriverHelper
{
	private   $driverName;
	protected $fallbackDriverName;
	
	/**
	 * @var $this
	 */
	protected $FallbackDriver;
	
	public function __construct(string $driverName)
	{
		$this->driverName = $driverName;
		if ($this->fallbackDriverName === $this->driverName)
		{
			Cachly::error('Fallback driver cannot be the same to self');
		}
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
	 * @inheritDoc
	 */
	public final function set(string $CID, $data, int $expires = 0): bool
	{
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
	 * @inheritDoc
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
	 * @inheritDoc
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
	 * @inheritDoc
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
	 * @inheritDoc
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
	 * @inheritDoc
	 */
	public final function debug()
	{
		debug($this->getItems());
	}
	
	/**
	 * @inheritDoc
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
	 * @inheritDoc
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