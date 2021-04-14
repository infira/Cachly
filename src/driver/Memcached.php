<?php

namespace Infira\Cachly\driver;

use Infira\Cachly\Cachly;
use Infira\Cachly\options\MemcachedDriverOptions;

class Memcached extends \Infira\Cachly\DriverHelper
{
	/**
	 * @var \Memcached $MemCached
	 */
	public $Memcached;
	
	/**
	 * @var MemcachedDriverOptions
	 */
	private $Options;
	
	public function __construct()
	{
		$this->setDriver(Cachly::MEM);
		if (!self::isConfigured())
		{
			Cachly::error("Memcached driver can't be used because its not configured. Use Cachly::configMemcached");
		}
		
		$this->Options            = Cachly::getOpt('redisOptions');
		$this->fallbackDriverName = $this->Options->fallbackDriver;
		
		if ($this->Options->client === null)
		{
			if (!class_exists('Memcached'))
			{
				$this->fallbackORShowError('Memcached class does not exists, make sure that memcached is installed');
			}
			$this->Memcached = new \Memcached();
			$connect         = $this->Memcached->addServer($this->Options->host, intval($this->Options->port));
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
			if (is_callable($this->Options->afterConnect) and $ok)
			{
				call_user_func_array($this->Options->afterConnect, [$this->Memcached]);
			}
		}
		else
		{
			$this->Memcached = $this->Options->client;
			if (!is_object($this->Memcached))
			{
				Cachly::error("client must be object");
			}
			if (!$this->Memcached instanceof \Memcached)
			{
				Cachly::error("client must be Memcached class");
			}
		}
		parent::__construct();
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
	public static function isConfigured(): bool
	{
		return Cachly::getOpt('memcachedOptions') !== null;
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