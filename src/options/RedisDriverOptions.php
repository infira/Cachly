<?php

namespace Infira\Cachly\options;

use Infira\Cachly\Cachly;

class RedisDriverOptions
{
	public $fallbackDriver = Cachly::SESS;
	public $afterConnect   = null;
	/**
	 * @var \Redis()
	 */
	public $client;
	public $host     = 'localhost';
	public $password = null;
	public $port     = 6379;
}