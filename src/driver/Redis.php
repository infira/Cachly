<?php

namespace Infira\Cachly\driver;

use Infira\Cachly\Cachly;

class Redis extends \Infira\Cachly\DriverHelper
{
	/**
	 * @var \Redis
	 */
	private $Redis;
	
	public function __construct()
	{
		$this->setDriver(Cachly::REDIS);
		if (!$this->isConfigured())
		{
			Cachly::error("Redis driver can't be used because its not configured. Use Cachly::configRedis");
		}
		$this->fallbackDriverName = Cachly::getOpt('redisFallbackDriver');
		if (Cachly::getOpt('redisClient'))
		{
			$this->Redis = Cachly::getOpt('redisClient');
			if (!is_object($this->Redis))
			{
				Cachly::error("client must be object");
			}
			if (!$this->Redis instanceof \Redis)
			{
				Cachly::error("client must be Redis class");
			}
		}
		elseif (class_exists("Redis"))
		{
			try
			{
				$this->Redis = new \Redis();
				$connect     = $this->Redis->pconnect(Cachly::getOpt('redisHost'), Cachly::getOpt('redisPort'));
				if (!$connect)
				{
					$this->fallbackORShowError("Redis connection failed");
				}
				else
				{
					$this->Redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
					if (!$this->Redis->auth(Cachly::getOpt('redisPassword')) and Cachly::getOpt('redisPassword'))
					{
						$this->fallbackORShowError("Redis connection authentication failed");
					}
					else
					{
						if (is_callable(Cachly::getOpt('redisAfterConnect')))
						{
							$f = Cachly::getOpt('redisAfterConnect');
							$f->call($this->Redis);
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
			$this->fallbackORShowError('Redis class does not exists, make sure that redis is installed');
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