<?php

namespace Infira\Cachly;

use Infira\Utils\ClassFarm;
use Infira\Poesis\Poesis;
use Infira\Cachly\options\RedisDriverOptions;
use Infira\Cachly\options\MemcachedDriverOptions;
use Infira\Cachly\options\DbDriverOptions;
use Infira\Cachly\options\FileDriverOptions;


/**
 * Cachly
 * @method static Cacher Collection(string $key) - Makes a cache collection
 * @method static void  each(callable $callback) - Call $callback for every item<br />$callback($value, $cacheKey)
 * @method static void  eachCollection(callable $callback) - Call $callback for every sub collection every item<br />$callback($value, $cacheKey, $collecitonName)
 * @method static array  getCollections() - Get all current collections
 * @method static string  set(string $key = '', $value, $ttl = 0) - Set cache value, returns cacheID which was used to save $value
 * @method static DriverHelper getDriver() - Get instance currrent driver
 * @method static mixed  get(string $key = '', $returnOnNotExists = null) - Get cache item
 * @method static array  getMulti(array $keys) - Get multiple items by keys
 * @method static bool  exists(string $key = '') - Does cache item exists by key
 * @method static array  getRegex(string $pattern) - Get cache items by regular expression
 * @method static bool  isExpired(string $key = '') - Is cache item expired
 * @method static string|null|integer  expiresAt(string $key = '') - Tells when cache item expires: "expired", "never" or timestamp when will be expired, returns null when not exists
 * @method static bool  delete(string $key = '')  - Delete cache item
 * @method static bool  deleteRegex(string $pattern) - Delete by regular expression
 * @method static bool  deletedExpired()- Delete expired items from cache
 * @method static bool  flush() - Flush current instance/collection
 * @method static array  getItems() - Get all current instance/collection cache items
 * @method static void  debug() - Dumps current instance/collection items
 * @method static mixed  once(string|array|int $key, callable $callback, mixed $callbackArg1 = null, mixed $callbackArg2 = null, mixed $callbackArg3 = null, mixed $callbackArg_n = null) - Call $callback once per cache existance, result will be seted to cache
 * @method static mixed  onceForce(string|array|int $key, callable $callback, bool $forceSet, mixed $callbackArg1, mixed $callbackArg2, mixed $callbackArg3, mixed $callbackArg_n) - Call $callback once per $key existence or force it to call
 * @method static mixed  onceExpire(string|array|int $key, callable $callback, int|string $ttl, mixed $callbackArg1 = null, mixed $callbackArg2 = null, mixed $callbackArg3 = null, mixed $callbackArg_n = null) - Call $callback once per $key existence or when its expired
 * @method static array  getInstancesKeys() - Returns array of all current driver instance/collection keys
 * @method static array  getIDKeyPairs() - Get instance/collection cacheKey/cacheID pairs
 * @method static array  getIDS() - Get instance/collection cache IDS
 * @method static array  getKeys() - Get instance/collection cache keys
 */
class Cachly
{
	public static $options = ['defaultDriver' => 'sess', 'redisOptions' => null, 'memcachedOptions' => null, 'dbOptions' => null, 'fileOptions' => null];
	
	/**
	 * @var DriverNode
	 */
	public static $Driver;
	
	/**
	 * @var Cacher $DefaultDriverDefaultInstance
	 */
	private static $DefaultDriverDefaultInstance;
	
	private static $hashAlgorithm = 'sha1';
	
	const DB             = 'db';
	const FILE           = 'file';
	const MEM            = 'mem';
	const REDIS          = 'redis';
	const RUNTIME_MEMORY = 'rm';
	const SESS           = 'sess';
	
	/**
	 * Call default instance method
	 *
	 * @param $method
	 * @param $args
	 * @return mixed
	 */
	public final static function __callStatic($method, $args)
	{
		if (self::$DefaultDriverDefaultInstance === null)
		{
			self::error("Cachly default driver is not set, use Cachly::setDefaultDriver");
		}
		
		return self::$DefaultDriverDefaultInstance->$method(...$args);
	}
	
	/**
	 * Initializes Cachly
	 *
	 * @param array $options
	 */
	public final static function init(array $options = []): void
	{
		self::$Driver = new DriverNode();
		if (isset($options['defaultDriver']))
		{
			self::setDefaultDriver($options['defaultDriver']);
		}
	}
	
	/**
	 * Set default driver
	 *
	 * @param string $name
	 * @return void
	 */
	public final static function setDefaultDriver(string $name): void
	{
		if (!self::$Driver)
		{
			self::error("Cachly is not initialized use Cachly::init");
		}
		self::$options['defaultDriver']     = $name;
		self::$DefaultDriverDefaultInstance = self::di($name, 'cachly');
	}
	
	
	public final static function getDefaultDriver(): string
	{
		if (!self::$Driver)
		{
			self::error("Cachly is not initialized use Cachly::init");
		}
		if (!isset(self::$options['defaultDriver']))
		{
			self::error("Cachly default driver is not set");
		}
		
		return self::$options['defaultDriver'];
	}
	
	/**
	 * Set hashing algorithm
	 *
	 * @param string $name crc32,md5 or sha1(default)
	 * @see https://stackoverflow.com/questions/3665247/fastest-hash-for-non-cryptographic-uses/5021846
	 * @return void
	 */
	public final static function setHashingAlgorithm(string $name): void
	{
		if (!in_array($name, ['crc32', 'md5', 'sha1']))
		{
			self::error("Unknown hashing algorythm");
		}
		self::$hashAlgorithm = $name;
	}
	
	/**
	 * Configure redis driver
	 *
	 * @param RedisDriverOptions $options
	 * @throws \Infira\Poesis\Error
	 * @see https://github.com/phpredis/phpredis
	 */
	public final static function configRedis(RedisDriverOptions &$options)
	{
		if ($options === null)
		{
			$options = new RedisDriverOptions();
		}
		if ($options->client === null and !$options->host)
		{
			self::error('Fill client property with Redis object or (user,host,port) properties to make inner mysql connection');
		}
		self::$options['redisOptions'] = $options;
	}
	
	/**
	 * Configure memcached driver
	 *
	 * @param MemcachedDriverOptions $options
	 * @see https://www.php.net/manual/en/book.memcached.php
	 */
	public final static function configureMemcached(MemcachedDriverOptions $options)
	{
		if ($options === null)
		{
			$options = new MemcachedDriverOptions();
		}
		if ($options->client === null and !$options->host)
		{
			self::error('Fill client property with Memcached object or (host,port) properties to make inner mysql connection');
		}
		self::$options['memcachedOptions'] = $options;
	}
	
	/**
	 * Configure database driver
	 *
	 * @param DbDriverOptions $options
	 * @throws \Infira\Poesis\Error
	 * @see https://www.php.net/manual/en/book.mysqli.php
	 */
	public final static function configureDb(DbDriverOptions $options)
	{
		if ($options === null)
		{
			$options = new DbDriverOptions();
		}
		if ($options->client === null and !$options->host)
		{
			self::error('Fill client property with mysqli object or (user,password,host,port) properties to make inner mysql connection');
		}
		self::$options['dbOptions'] = $options;
	}
	
	/**
	 * Configure file driver
	 *
	 * @param FileDriverOptions $options
	 */
	public final static function configureFile(FileDriverOptions $options)
	{
		if ($options === null)
		{
			$options = new FileDriverOptions();
		}
		self::$options['fileOptions'] = $options;
	}
	
	public final static function isConfigured(string $driver): bool
	{
		$optName = null;
		if ($driver == self::DB)
		{
			$optName = 'dbOptions';
		}
		elseif ($driver == self::FILE)
		{
			$optName = 'fileOptions';
		}
		elseif ($driver == self::MEM)
		{
			$optName = 'memcachedOptions';
		}
		elseif ($driver == self::REDIS)
		{
			$optName = 'redisOptions';
		}
		
		return Cachly::getOpt($optName) !== null;
	}
	
	/**
	 * Get stored option value
	 *
	 * @param string $name
	 * @return mixed
	 */
	public final static function getOpt(string $name)
	{
		if (!array_key_exists($name, self::$options))
		{
			self::error("option '$name' not found");
		}
		
		return self::$options[$name];
	}
	
	/**
	 * Shortcut to default driver instance cacher
	 *
	 * @param string $instance
	 * @return Cacher
	 */
	public final static function instance(string $instance = 'cachly'): Cacher
	{
		if ($instance == 'cachly')
		{
			return self::$DefaultDriverDefaultInstance;
		}
		
		return self::di(self::$options['defaultDriver'], $instance);
	}
	
	/**
	 * Shortcut to builtin database driver cacher
	 *
	 * @param string $instance
	 * @return Cacher
	 */
	public final static function db(string $instance = 'cachly'): Cacher
	{
		return self::di(self::DB, $instance);
	}
	
	/**
	 * Shortcut to builtin file driver cacher
	 *
	 * @param string $instance
	 * @return Cacher
	 */
	public final static function file(string $instance = 'cachly'): Cacher
	{
		return self::di(self::FILE, $instance);
	}
	
	/**
	 * Shortcut to builtin memcached driver cacher
	 *
	 * @param string $instance
	 * @return Cacher
	 */
	public final static function mem(string $instance = 'cachly'): Cacher
	{
		return self::di(self::MEM, $instance);
	}
	
	/**
	 * Shortcut to builtin redis driver cacher
	 *
	 * @param string $instance
	 * @return Cacher
	 */
	public final static function redis(string $instance = 'cachly'): Cacher
	{
		return self::di(self::REDIS, $instance);
	}
	
	/**
	 * Shortcut to builtin sesson driver cacher
	 *
	 * @param string $instance
	 * @return Cacher
	 */
	public final static function sess(string $instance = 'cachly'): Cacher
	{
		return self::di(self::SESS, $instance);
	}
	
	/**
	 * Shortcut to builtin runtimememory driver cacher
	 *
	 * @param string $instance
	 * @return Cacher
	 */
	public final static function rm(string $instance = 'cachly'): Cacher
	{
		return self::di(self::RUNTIME_MEMORY, $instance);
	}
	
	/**
	 * Shortcut to driver cacher instance by name
	 *
	 * @param string $driver
	 * @param string $instance
	 * @return Cacher
	 */
	public final static function di(string $driver, string $instance = 'cachly'): object
	{
		return ClassFarm::instance("Cachly->$driver->$instance", function () use ($instance, $driver)
		{
			return new Cacher($instance, self::$Driver->make($driver));
		});
	}
	
	####################### Start of helpers
	
	/**
	 * @param string $msg
	 * @throws \Error
	 */
	public static function error(string $msg)
	{
		throw new \Error("Cachly says: " . $msg);
	}
	
	public static function hash(string $hashable)
	{
		$method = self::$hashAlgorithm;
		$hash   = $method($hashable);
		
		/*
		 * Make sure that cacheIDS always starts with a letter
		 * https://stackoverflow.com/questions/18797251/notice-unknown-skipping-numeric-key-1-in-unknown-on-line-0
		 * Just in caselets to all the cacheIDS prefix
		 */
		
		//$hash = 'c' . $hash;
		
		return $hash;
	}
}

?>