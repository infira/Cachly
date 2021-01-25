<?php

namespace Infira\Cachly;

use Infira\Poesis\Poesis;

class RedisDriverOptions extends DriverOptions
{
	/**
	 * @var \Redis()
	 */
	public $client;
	public $host     = null;
	public $password = null;
	public $port     = 0;
}