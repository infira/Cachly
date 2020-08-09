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
	private $constructedPropDrivers = [];
	
	private $driverConstructors = [];
	
	public function __construct()
	{
		//register built drivers
		$this->doAdd(Cachly::DB, 'Db', '\Infira\Cachly\driver\Db');
		$this->doAdd(Cachly::FILE, 'File', '\Infira\Cachly\driver\File');
		$this->doAdd(Cachly::MEM, 'Mem', '\Infira\Cachly\driver\Memcached');
		$this->doAdd(Cachly::REDIS, 'Redis', '\Infira\Cachly\driver\Redis');
		$this->doAdd(Cachly::SESS, 'Sess', '\Infira\Cachly\driver\Session');
		$this->doAdd(Cachly::RUNTIME_MEMORY, 'Rm', '\Infira\Cachly\driver\RuntimeMemory');
	}
	
	/**
	 * Construct driver
	 *
	 * @param string $name or builtint type name
	 * @param \Infira\Cachly\DriverHelper
	 */
	public function make(string $name): object
	{
		$constructor = null;
		if (isset($this->driverConstructors[$name]))
		{
			$constructor = $this->driverConstructors[$name];
			if (is_string($constructor)) // it means built in driver
			{
				$className   = $constructor;
				$constructor = function () use ($className)
				{
					return new $className;
				};
			}
		}
		else
		{
			Cachly::error("Unknown driver");
		}
		
		return ClassFarm::instance("Cachly->Driver->$name", $constructor);
	}
	
	public function __get($property)
	{
		if (!isset($this->constructedPropDrivers[$property]))
		{
			Cachly::error("Driver property($property) not found");
		}
		$this->$property = $this->make($this->constructedPropDrivers[$property]);
		
		return $this->$property;
	}
	
	
	/**
	 * Add new driver
	 *
	 * @param string   $driverName
	 * @param string   $propertyName - $DriverNode property name is used to accss this driver (Cachly::$Driver->myDriver....)
	 * @param callable $constructor
	 */
	public final function add(string $driverName, string $propertyName, callable $constructor)
	{
		$this->doAdd($driverName, $propertyName, $constructor);
	}
	
	/**
	 * For internal use
	 *
	 * @param string          $driverName
	 * @param string          $propertyName
	 * @param string|callable $constructor
	 */
	private final function doAdd(string $driverName, string $propertyName, $constructor)
	{
		if (isset($this->driverConstructors[$driverName]))
		{
			Cachly::error("driver($driverName) is already registered");
		}
		$this->driverConstructors[$driverName]       = $constructor;
		$this->constructedPropDrivers[$propertyName] = $driverName;
	}
}