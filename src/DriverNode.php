<?php

namespace Infira\Cachly;

use Infira\Utils\ClassFarm;

/**
 * Class DriverNode
 *
 * @property \Infira\Cachly\driver\Db            $Db
 * @property \Infira\Cachly\driver\File          $File
 * @property \Infira\Cachly\driver\Memcached     $Mem
 * @property \Infira\Cachly\driver\Redis         $Redis
 * @property \Infira\Cachly\driver\Session       $Sess
 * @property \Infira\Cachly\driver\RuntimeMemory $Rm
 * @package Infira\Cachly
 */
class DriverNode
{
	private $registeredPropertyDrivers = [];
	
	private $registeredDrivers = [];
	
	public function __construct()
	{
		//register built drivers
		$this->register(Cachly::DB, 'Db', '\Infira\Cachly\driver\Db');
		$this->register(Cachly::FILE, 'File', '\Infira\Cachly\driver\File');
		$this->register(Cachly::MEM, 'Mem', '\Infira\Cachly\driver\Memcached');
		$this->register(Cachly::REDIS, 'Redis', '\Infira\Cachly\driver\Redis');
		$this->register(Cachly::SESS, 'Sess', '\Infira\Cachly\driver\Session');
		$this->register(Cachly::RUNTIME_MEMORY, 'Rm', '\Infira\Cachly\driver\RuntimeMemory');
	}
	
	public function __get(string $property)
	{
		if (!isset($this->registeredPropertyDrivers[$property]))
		{
			Cachly::error("Driver property($property) not found");
		}
		$driver = $this->registeredPropertyDrivers[$property];
		if (!isset($this->registeredDrivers[$driver]))
		{
			Cachly::error("Unknown driver = " . $driver);
		}
		
		$constructor = $this->registeredDrivers[$driver]->constructor;
		if (is_string($constructor)) // it means built in driver
		{
			$className   = $constructor;
			$constructor = function () use ($className)
			{
				return new $className;
			};
		}
		$this->registeredDrivers[$driver]->isConstructed = true;
		
		$this->$property = ClassFarm::instance("Cachly->Driver->$driver", $constructor);
		
		return $this->$property;
	}
	
	public function get(string $driver): DriverHelper
	{
		if (!isset($this->registeredDrivers[$driver]))
		{
			Cachly::error('Driver is not registered');
		}
		$property = $this->registeredDrivers[$driver]->property;
		
		return $this->$property;
	}
	
	public function isConstructed(string $driver): bool
	{
		if (!isset($this->registeredDrivers[$driver]))
		{
			return false;
		}
		
		return $this->registeredDrivers[$driver]->isConstructed;
	}
	
	/**
	 * Add new driver
	 *
	 * @param string          $driver
	 * @param string          $property
	 * @param string|callable $constructor
	 */
	public final function register(string $driver, string $property, $constructor)
	{
		if (isset($this->registeredDrivers[$driver]))
		{
			Cachly::error("driver($driver) is already registered");
		}
		$this->registeredDrivers[$driver]           = (object)['property' => $property, 'constructor' => $constructor, 'isConstructed' => false];
		$this->registeredPropertyDrivers[$property] = $driver;
	}
}