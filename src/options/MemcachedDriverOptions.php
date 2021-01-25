<?php

namespace Infira\Cachly\options;

use Infira\Cachly\Cachly;

class MemcachedDriverOptions
{
	public $fallbackDriver = Cachly::SESS;
	public $afterConnect   = null;
	/**
	 * @var \Memcached()
	 */
	public $client;
	public $host = 'localhost';
	public $port = 11211;
}