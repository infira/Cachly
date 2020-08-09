<?php

namespace Infira\Cachly\driver;

use Infira\Cachly\Cachly;

class Memcached extends \Infira\Cachly\DriverHelper
{
	/**
	 * @var \Memcached $MemCached
	 */
	public $Memcached;
	
	
	public function __construct()
	{
		if (!Cachly::getOpt('memcachedConfigured'))
		{
			Cachly::error("Memcached driver can't be used because its not configured. Use Cachly::configMemcached");
		}
		
		$this->fallbackDriverName = Cachly::getOpt('memcachedFallbackDriver');
		if (Cachly::getOpt('memcachedClient'))
		{
			$this->Memcached = Cachly::getOpt('memcachedClient');
			if (!is_object($this->Memcached))
			{
				Cachly::error("client must be object");
			}
			if (!$this->Memcached instanceof \Memcached)
			{
				Cachly::error("client must be Memcached class");
			}
		}
		elseif (class_exists("Memcached"))
		{
			$this->Memcached = new \Memcached();
			$connect         = $this->Memcached->addServer(Cachly::getOpt('memcachedHost'), intval(Cachly::getOpt('memcachedPort')));
			$ok              = true;
			if ($this->Memcached->getStats() === false)
			{
				$this->fallbackORShowError('Memcached connection failed');
				$ok = false;
			}
			if (!$connect)
			{
				$this->fallbackORShowError('Memcached connection failed');
				$ok = false;
			}
			if (is_callable(Cachly::getOpt('memcachedAfterConnect')) and $ok)
			{
				$f = Cachly::getOpt('memcachedAfterConnect');
				$f->call($this->Memcached);
			}
		}
		else
		{
			$this->fallbackORShowError('Memcached class does not exists, make sure that memcached is installed');
		}
		parent::__construct(Cachly::MEM);
	}
	
	/**
	 * Get client
	 *
	 * @return \Memcached
	 */
	public function getClient(): \Memcached
	{
		return $this->Memcached;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doSet(string $CID, $data, int $expires = 0): bool
	{
		return $this->Memcached->set($CID, $data, $expires);
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doExists(string $CID): bool
	{
		return $this->Memcached->get($CID) !== false || $this->Memcached->getResultCode() != \Memcached::RES_NOTFOUND;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGet(string $CID)
	{
		return $this->Memcached->get($CID);
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doDelete(string $CID): bool
	{
		return $this->Memcached->delete($CID);
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGetItems(): array
	{
		$keys = $this->Memcached->getAllKeys();
		if ($keys === false)
		{
			return [];
		}
		
		return $this->Memcached->getMulti($keys);
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doFlush(): bool
	{
		return $this->Memcached->flush();
	}
	
	public function getStats()
	{
		$stats                      = $this->Memcached->getStats();
		$stats                      = $stats["localhost:11211"];
		$stats["megaBytes"]         = $stats["bytes"] / 1048576;
		$stats["limitMaxMegaBYtes"] = $stats["limit_maxbytes"] / 1048576;
		
		return $stats;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGc(): bool
	{
		$now = time();
		foreach ($this->doGetItems() as $CID => $v)
		{
			if (is_object($v) and isset($v->t) and $now > $v->t)
			{
				self::doDelete($CID);
			}
		}
		
		return true;
	}
}

?>