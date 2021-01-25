<?php

namespace Infira\Cachly\options;

use Infira\Cachly\Cachly;

class DbDriverOptions
{
	public $fallbackDriver = Cachly::SESS;
	public $afterConnect   = null;
	
	public $table = 'cachly_cache';
	
	/**
	 * @var \mysqli
	 */
	public $client;
	public $host     = 'localhost';
	public $user     = null;
	public $password = null;
	public $db       = null;
	public $port     = null; //null = default port will be used
}