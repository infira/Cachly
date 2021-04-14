<?php

namespace Infira\Cachly\driver;

use Infira\Cachly\Cachly;
use Infira\Cachly\options\RedisDriverOptions;

class Redis extends \Infira\Cachly\DriverHelper
{
	/**
	 * @var \Redis
	 */
	private $Redis;
	
	/**
	 * @var RedisDriverOptions
	 */
	private $Options;
	
	public function __construct()
	{
		$this->setDriver(Cachly::REDIS);
		if (!self::isConfigured())
		{
			Cachly::error("Redis driver can't be used because its not configured. Use Cachly::configRedis");
		}
		$this->Options            = Cachly::getOpt('redisOptions');
		$this->fallbackDriverName = $this->Options->fallbackDriver;
		
		if ($this->Options->client === null)
		{
			try
			{
				if (!class_exists("Redis"))
				{
					$this->fallbackORShowError('Redis class does not exists, make sure that redis is installed');
				}
				$this->Redis = new \Redis();
				$connect     = $this->Redis->pconnect($this->Options->host, $this->Options->port);
				if (!$connect)
				{
					$this->fallbackORShowError("Redis connection failed");
				}
				else
				{
					$this->Redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
					if (!$this->Redis->auth($this->Options->password))
					{
						$this->fallbackORShowError("Redis connection authentication failed");
					}
					else
					{
						if (is_callable($this->Options->afterConnect))
						{
							$f = $this->Options->afterConnect;
							call_user_func_array($f, [$this->Redis]);
						}
					}
				}
			}
			catch (\Exception $exception)
			{
				$this->fallbackORShowError($exception->getMessage());
			}
			
		}
		else
		{
			$this->Redis = $this->Options->client;
			if (!is_object($this->Redis))
			{
				$this->fallbackORShowError("client must be object");
			}
			if (!$this->Redis instanceof \Redis)
			{
				$this->fallbackORShowError("client must be Redis class");
			}
		}
		parent::__construct();
	}
	
	/**
	 * Get client
	 *
	 * @return \Redis
	 */
	public function getClient(): \Redis
	{
		return $this->Redis;
	}
	
	/**
	 * @inheritDoc
	 */
	public static function isConfigured(): bool
	{
		return Cachly::getOpt('redisOptions') !== null;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doSet(string $CID, $data, int $expires = 0): bool
	{
		$setted = $this->Redis->set($CID, $data);
		if ($expires > 0)
		{
			$this->Redis->expireAt($CID, $expires);
		}
		
		return $setted;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doExists(string $CID): bool
	{
		return $this->Redis->exists($CID);
		
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGet(string $CID)
	{
		return $this->Redis->get($CID);
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doDelete(string $CID): bool
	{
		$this->Redis->del($CID);
		
		return true;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGetItems(): array
	{
		$output = [];
		foreach ($this->Redis->keys('*') as $CID)
		{
			$output[$CID] = $this->get($CID);
		}
		
		return $output;//$this->Redis->mGet($this->Redis->keys('*'));
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doFlush(): bool
	{
		return $this->Redis->flushDB();
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