<?php

namespace Infira\Cachly;

use Infira\Poesis\Poesis;

class MemcachedDriverOptions extends DriverOptions
{
	/**
	 * @var \Memcached()
	 */
	public $client;
	public $host = 'localhost';
	public $port = 11211;
}