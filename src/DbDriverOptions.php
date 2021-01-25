<?php

namespace Infira\Cachly;

use Infira\Poesis\Poesis;

class DbDriverOptions extends DriverOptions
{
	public $table = 'cachly_cache';
	
	/**
	 * @var \mysqli
	 */
	public $client;
	public $host     = null;
	public $user     = null;
	public $password = null;
	public $db       = null;
	public $port     = 0; //if 0 then mysql default port will be used
}