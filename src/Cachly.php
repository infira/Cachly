<?php

namespace Infira\Cachly;

use Infira\Utils\ClassFarm;


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
	public static $options = ['defaultDriver' => 'sess', 'redisConfigured' => false, 'redisClient' => null, 'memcachedConfigured' => false, 'memcachedClient' => null, 'dbConfigured' => false, 'dbClient' => null, 'fileConfigured' => false];
	
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
		return self::$DefaultDriverDefaultInstance->$method(...$args);
	}
	
	/**
	 * Initializes Cachly
	 */
	public final static function init(): void
	{
		self::$Driver = new DriverNode();
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
	 * @param array|\Redis $redis - \Redis class or options array ['password'=>'', 'host'=>'localhost', 'port'=>6379, 'afterConnect'=>null|callable, 'fallbackDriver'=>null|string]
	 * @see https://github.com/phpredis/phpredis
	 */
	public final static function configRedis($redis)
	{
		$options                    = [];
		$options['redisConfigured'] = true;
		if (is_object($redis) and $redis instanceof \Redis)
		{
			$options['redisClient'] = &$redis;
			self::$options          = array_merge(self::$options, $options);
		}
		else
		{
			$defaultOptions                 = array_merge(['password' => '', 'host' => 'localhost', 'port' => 6379, 'afterConnect' => null, 'fallbackDriver' => null], $redis);
			$options['redisHost']           = $defaultOptions['host'];
			$options['redisPort']           = intval($defaultOptions['port']);
			$options['redisPassword']       = $defaultOptions['password'];
			$options['redisFallbackDriver'] = (is_string($defaultOptions['fallbackDriver'])) ? $defaultOptions['fallbackDriver'] : null;
			$options['redisAfterConnect']   = (is_callable($defaultOptions['afterConnect'])) ? $defaultOptions['afterConnect'] : null;
			self::$options                  = array_merge(self::$options, $options);
		}
	}
	
	/**
	 * Configure memcached driver
	 *
	 * @param array|\Memcached $memcached - \Memcached class or options array ['host'=>'localhost', 'port'=>11211, 'afterConnect'=>null|callable, 'fallbackDriver'=>null|string]
	 * @see https://www.php.net/manual/en/book.memcached.php
	 */
	public final static function configureMemcached($memcached)
	{
		$options = ['memcachedConfigured' => true];
		if (is_object($memcached) and $memcached instanceof \Memcached)
		{
			$options                    = [];
			$options['memcachedClient'] = &$memcached;
			self::$options              = array_merge(self::$options, $options);
		}
		else
		{
			$defaultOptions                     = array_merge(['host' => 'localhost', 'port' => 11211, 'afterConnect' => null, 'fallbackDriver' => null], $memcached);
			$options['memcachedHost']           = $defaultOptions['host'];
			$options['memcachedPort']           = intval($defaultOptions['port']);
			$options['memcachedFallbackDriver'] = (is_string($defaultOptions['fallbackDriver'])) ? $defaultOptions['fallbackDriver'] : null;
			$options['memcachedAfterConnect']   = (is_callable($defaultOptions['afterConnect'])) ? $defaultOptions['afterConnect'] : null;
			self::$options                      = array_merge(self::$options, $options);
		}
	}
	
	/**
	 * Configure database driver
	 *
	 * @param array|\mysqli $db - \mysqli class or options array ['user'=>'', 'password'=>'', 'host'=>'localhost', 'port'=>'ini_get("mysqli.default_port")', 'db'=>'myDbName', 'table'=>'cachly_data', 'afterConnect'=>null|callable, 'fallbackDriver'=>null|string] <br />
	 *                          Leave port empty to use system configured mysql port (ini_get("mysqli.default_port"))
	 * @see https://www.php.net/manual/en/book.mysqli.php
	 */
	public final static function configureDb($db)
	{
		$options = ['dbConfigured' => true];
		if (is_object($db) and $db instanceof \mysqli)
		{
			$options['dbClient']       = &$db;
			$options['dbAfterConnect'] = null;
			self::$options             = array_merge(self::$options, $options);
		}
		else
		{
			$defaultOptions              = array_merge(['user' => '', 'password' => '', 'host' => 'localhost', 'port' => '', 'db' => 'myDbName', 'table' => 'cachly_data', 'afterConnect' => null, 'fallbackDriver' => null], $db);
			$options['dbHost']           = $defaultOptions['host'];
			$options['dbPort']           = intval($defaultOptions['port']);
			$options['dbUser']           = $defaultOptions['user'];
			$options['dbPass']           = $defaultOptions['password'];
			$options['dbDatabase']       = $defaultOptions['db'];
			$options['dbTable']          = $defaultOptions['table'];
			$options['dbFallbackDriver'] = (is_string($defaultOptions['fallbackDriver'])) ? $defaultOptions['fallbackDriver'] : null;
			$options['dbAfterConnect']   = (is_callable($defaultOptions['afterConnect'])) ? $defaultOptions['afterConnect'] : null;
			self::$options               = array_merge(self::$options, $options);
		}
	}
	
	/**
	 * Configure file driver
	 *
	 * @param string $path
	 * @param string $fallbackDriver - in case of redis connection error use fallback driver
	 */
	public final static function configureFile(string $path = '', $fallbackDriver = null)
	{
		$options                       = [];
		$options['fileConfigured']     = true;
		$options['filePath']           = $path;
		$options['fileFallbackDriver'] = (is_string($fallbackDriver)) ? $fallbackDriver : null;
		self::$options                 = array_merge(self::$options, $options);
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
	 * @return object
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