<?php

namespace Infira\Cachly;

use DateInterval;
use DateTimeInterface;
use Infira\Cachly\Adapter\SessionAdapter;
use Infira\Cachly\options\DbAdapterOptions;
use Infira\Cachly\options\FileSystemAdapterOptions;
use Infira\Cachly\options\MemcachedAdapterOptions;
use Infira\Cachly\options\RedisAdapterOptions;
use Infira\Cachly\Support\AdapterRegister;
use Infira\Cachly\Support\CacheInstanceRegister;
use Infira\Cachly\Support\Collection;
use Psr\Cache\CacheItemInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * @template TNamespace
 * @method static CacheInstance sub(string $key)
 * @method static CacheItem put(string $key, mixed $value, int|string|DateTimeInterface|DateInterval|null $expires = null) - saves value immediately
 * @method static CacheItem set(string $key, mixed $value)
 * @method static bool save(CacheItemInterface $item)
 * @method static bool saveDeferred(CacheItemInterface $item)
 * @method static CacheItem getItem(string $key)
 * @method static \Generator<string, CacheItem> getItems(string[] $key)
 * @method static mixed get(string $key, mixed $value = null)
 * @method static mixed once(mixed ...$params) Execute $callback once by hash-sum of $parameters
 * @method static array getValues(array $keys, mixed $default = null)
 * @method static bool has(string $key)
 * @method static bool isExpired(string $key)
 * @method static bool forget(string|string[]|callable $key)
 * @method static bool delete(string $key)
 * @method static bool deleteItem(string $key)
 * @method static bool deleteItems(string[] $keys)
 * @method static bool forgetByRegex(string $keyPattern)
 * @method static bool commit()
 * @method static bool clear()
 * @method static bool prune()
 * @method static array getKeys()
 * @method static array all()
 * @method static array toArray()
 * @method static void each(callable $callback)
 * @method static Collection map(callable $callback)
 * @method static Collection filter(callable $callback)
 * @method static Collection filterRegex(string $keyPattern)
 * @method static Collection collect()
 * @method static mixed pipeInto(string $key, string $class, mixed $defaultValue = [])
 * @method static AbstractAdapter getAdapter()
 * @method static string getAdapterName()
 * @method static string getNamespace()
 * @see CacheInstance
 */
class Cachly
{
    public static CacheInstance $sess;
    public static CacheInstance $mem;
    public static CacheInstance $db;
    public static CacheInstance $redis;
    public static CacheInstance $memCached;
    public static CacheInstance $file;

    public const DB = 'db';
    public const FILE = 'file';
    public const MEM = 'mem';
    public const REDIS = 'redis';
    public const MEM_CACHED = 'memCached';
    public const SESS = 'sess';
    public const DEFAULT_INSTANCE_NAME = 'cachly-production';

    public static array $options = [
        'defaultAdapter' => self::SESS,
        'memAdapter' => self::REDIS,
        'defaultInstanceName' => self::DEFAULT_INSTANCE_NAME,
    ];

    /**
     * Call default instance method
     *
     * @param $method
     * @param $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        return static::instance()->$method(...$args);
    }

    /**
     * Initializes Cachly
     *
     * @param  array  $options
     */
    public static function configure(array $options = []): void
    {
        self::$options = array_merge(self::$options, $options);
    }

    /**
     * Get stored option value
     *
     * @param  string  $name
     * @param  mixed|null  $default
     * @return mixed
     */
    public static function getOpt(string $name, mixed $default = null): mixed
    {
        if (!array_key_exists($name, self::$options)) {
            if (func_num_args() === 1) {
                throw new RuntimeException("option '$name' not found");
            }

            return $default;
        }

        return self::$options[$name];
    }

    public static function setDefaultAdapter(string $name): void
    {
        self::$options['defaultAdapter'] = $name;
    }

    public static function setPropertyInstances(array $properties): void
    {
        foreach ($properties as $property => $createInstance) {
            self::$$property = $createInstance();
        }
    }

    /**
     * @param  string  $name
     * @param  (callable(TNamespace):AbstractAdapter)  $constructor
     */
    public static function configureAdapter(string $name, callable $constructor): void
    {
        AdapterRegister::register($name, $constructor);
    }

    /**
     * Configure session adapter
     *
     * @param  (callable(TNamespace):AbstractAdapter)  $constructor
     * @see SessionAdapter
     */
    public static function configureSessionAdapter(callable $constructor): void
    {
        self::configureAdapter(static::SESS, $constructor);
    }

    /**
     * Configure redis adapter
     *
     * @param  (callable(TNamespace):RedisAdapter)|RedisAdapterOptions|array  $options
     * @see https://symfony.com/doc/current/components/cache/adapters/redis_adapter.html
     * @see RedisAdapter
     */
    public static function configureRedisAdapter(array|callable|RedisAdapterOptions $options): void
    {
        if (is_array($options)) {
            $options = new RedisAdapterOptions($options);
        }
        if ($options instanceof RedisAdapterOptions) {
            $constructor = static function ($namespace) use ($options) {
                $client = RedisAdapter::createConnection($options->get('dsn'), (array)$options);

                return new RedisAdapter($client, $namespace, $options->getDefaultLifeTime());
            };
        }
        else {
            $constructor = $options;
        }
        self::configureAdapter(static::REDIS, $constructor);
    }

    /**
     * Configure memcached adapter
     *
     * @param  (callable(TNamespace):MemcachedAdapter)|MemcachedAdapterOptions|array  $options
     * @see https://symfony.com/doc/current/components/cache/adapters/memcached_adapter.html
     * @see MemcachedAdapter
     */
    public static function configureMemcachedAdapter(array|callable|MemcachedAdapterOptions $options): void
    {
        if (is_array($options)) {
            $options = new MemcachedAdapterOptions($options);
        }
        if ($options instanceof MemcachedAdapterOptions) {
            $constructor = static function ($namespace) use ($options) {
                $client = MemcachedAdapter::createConnection($options->get('dsn'), $options->getOptions());

                return new MemcachedAdapter($client, $namespace, $options->getDefaultLifeTime());
            };
        }
        else {
            $constructor = $options;
        }
        self::configureAdapter(static::MEM_CACHED, $constructor);
    }

    /**
     * Configure database adapter
     *
     * @param  (callable(TNamespace):PdoAdapter)|DbAdapterOptions|array  $options
     * @see https://symfony.com/doc/current/components/cache/adapters/pdo_doctrine_dbal_adapter.html
     * @see PdoAdapter
     */
    public static function configureDbAdapter(array|callable|DbAdapterOptions $options): void
    {
        if (is_array($options)) {
            $options = new DbAdapterOptions($options);
        }
        if ($options instanceof DbAdapterOptions) {
            $constructor = static function ($namespace) use ($options) {
                return new PdoAdapter($options->get('dsn'), $namespace, $options->getDefaultLifeTime(), [
                    'db_table' => $options->get('table', 'cachly_cache'),
                    'db_username' => $options->get('user'),
                    'db_password' => $options->get('password')
                ]);
            };
        }
        else {
            $constructor = $options;
        }
        self::configureAdapter(static::DB, $constructor);
    }

    /**
     * Configure file adapter
     *
     * @param  (callable(TNamespace):FilesystemAdapter)|FileSystemAdapterOptions|array  $options
     * @see https://symfony.com/doc/current/components/cache/adapters/filesystem_adapter.html
     * @see FileSystemAdapterOptions
     */
    public static function configureFileSystemAdapter(array|callable|FileSystemAdapterOptions $options): void
    {
        if (is_array($options)) {
            $options = new FileSystemAdapterOptions($options);
        }
        if ($options instanceof FileSystemAdapterOptions) {
            $constructor = static function ($namespace) use ($options) {
                return new FilesystemAdapter($namespace, $options->getDefaultLifeTime(), $options->get('directory'));
            };
        }
        else {
            $constructor = $options;
        }
        self::configureAdapter(static::FILE, $constructor);
    }

    /**
     * Create cache instance with database adapter
     *
     * @param  string|null  $namespace  - if null $opt['defaultInstanceName'] - will be used
     * @return CacheInstance
     */
    public static function db(string $namespace = null): CacheInstance
    {
        return self::instance($namespace, self::DB);
    }

    /**
     * Create cache instance with file adapter
     *
     * @param  string|null  $namespace  - if null $opt['defaultInstanceName'] - will be used
     * @return CacheInstance
     */
    public static function file(string $namespace = null): CacheInstance
    {
        return self::instance($namespace, self::FILE);
    }

    /**
     * Create cache instance with memory adapter
     * Uses $options['memAdapter'] as adapter
     *
     * @param  string|null  $namespace  - if null $opt['defaultInstanceName'] - will be used
     * @return CacheInstance
     */
    public static function mem(string $namespace = null): CacheInstance
    {
        return self::instance($namespace, self::MEM);
    }

    /**
     * Create cache instance with redis adapter
     *
     * @param  string|null  $namespace  - if null $opt['defaultInstanceName'] - will be used
     * @return CacheInstance
     */
    public static function redis(string $namespace = null): CacheInstance
    {
        return self::instance($namespace, self::REDIS);
    }

    /**
     * Create cache instance with memCached adapter
     *
     * @param  string|null  $namespace  - if null $opt['defaultInstanceName'] - will be used
     * @return CacheInstance
     */
    public static function memCached(string $namespace = null): CacheInstance
    {
        return self::instance($namespace, self::MEM_CACHED);
    }

    /**
     * Create cache instance with session adapter
     *
     * @param  string|null  $namespace  - if null $opt['defaultInstanceName'] - will be used
     * @return CacheInstance
     */
    public static function sess(string $namespace = null): CacheInstance
    {
        return self::instance($namespace, self::SESS);
    }

    /**
     * Create cache instance
     *
     * @param  string|null  $namespace  - if null $opt['defaultInstanceName'] - will be used
     * @param  string|null  $adapterName  - if null $options['defaultAdapter'] will be used
     * @return CacheInstance
     */
    public static function instance(string $namespace = null, string $adapterName = null): CacheInstance
    {
        $adapterName = $adapterName ?: static::getOpt('defaultAdapter');
        $namespace = $namespace ?: static::getOpt('defaultInstanceName', static::DEFAULT_INSTANCE_NAME);

        return CacheInstanceRegister::get($namespace, $adapterName);
    }
    ####################### Start of helpers
}