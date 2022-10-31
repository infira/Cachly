<?php

namespace Infira\Cachly;

use Infira\Cachly\Adapter\SessionAdapter;
use Infira\Cachly\Exception\InvalidArgumentException;
use Infira\Cachly\options\DbAdapterOptions;
use Infira\Cachly\options\FileSystemAdapterOptions;
use Infira\Cachly\options\MemcachedAdapterOptions;
use Infira\Cachly\options\RedisAdapterOptions;
use Infira\Cachly\Support\Collection;
use Infira\Cachly\Support\Helpers;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;


/**
 * @method static CacheInstance sub(string $key)
 * @method static CacheInstance[]  getSubInstances()
 * @method static void  each(callable $callback)
 * @method static CacheInstance  putValue(string|int $key, $value, int|string $expires = 0)
 * @method static mixed  getValue(string|int $key, mixed $default = null)
 * @method static mixed  pipeInto(string|int $key, string $class, mixed $defaultValue = [])
 * @method static array  getMultipleValues(array $keys)
 * @method static bool  has(string|int $key)
 * @method static Collection  filter(callable $callback)
 * @method static Collection  map(callable $callback)
 * @method static Collection  filterRegex(string $pattern)
 * @method static bool  isExpired(string|int $key)
 * @method static string|null|integer  expiresAt(string|int $key)
 * @method static bool  forget(string|int $key)
 * @method static bool  forgetByRegex(string $pattern)
 * @method static bool  prune()
 * @method static void  clear()
 * @method static array  all()
 * @method static mixed  once(mixed ...$keys, callable $callback) Execute $callback once by hash-sum of $parameters
 * @method static array  getKeys()
 * @method static AbstractAdapter  getAdapter()
 * @see CacheInstance
 */
class Cachly
{
    public static ?AdaptersCollection $adapters = null;
    private static CacheInstance $instance;
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
        'cacheIDHashAlgorithm' => 'crc32b',
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
        if(self::$instance === null) {
            self::$instance = static::instance();
        }

        return self::$instance->$method(...$args);
    }

    /**
     * Initializes Cachly
     *
     * @param array $options
     */
    public static function configure(array $options = []): void
    {
        self::$adapters = new AdaptersCollection();
        self::$options = array_merge(self::$options, $options);
        if(isset(self::$options['cacheIDHashAlgorithm'])) {
            $algo = self::$options['cacheIDHashAlgorithm'];
            if(!in_array($algo, hash_algos(), true)) {
                throw new InvalidArgumentException("Unknown hashing algorithm('$algo)'");
            }
            self::$options['cacheIDHashAlgorithm'] = $algo;
        }
    }


    /**
     * Get stored option value
     *
     * @param string $name
     * @return mixed
     */
    public static function getOpt(string $name): mixed
    {
        if(!array_key_exists($name, self::$options)) {
            throw new InvalidArgumentException("option '$name' not found");
        }

        return self::$options[$name];
    }

    public static function hasOpt(string $name): bool
    {
        return array_key_exists($name, self::$options);
    }

    public static function getDefaultInstanceName(): string
    {
        if(!static::hasOpt('defaultInstanceName')) {
            return static::DEFAULT_INSTANCE_NAME;
        }

        return self::getOpt('defaultInstanceName');
    }

    public static function setDefaultAdapter(string $name): void
    {
        self::$options['defaultAdapter'] = $name;
    }

    public static function setPropertyInstances(array $properties): void
    {
        foreach($properties as $property => $createInstance) {
            self::$$property = $createInstance();
        }
    }

    /**
     * @param string $name
     * @param callable|AbstractAdapter $constructor
     */
    public static function configureAdapter(string $name, callable|AbstractAdapter $constructor): void
    {
        if(!self::$adapters) {
            throw new InvalidArgumentException('not initialized use Cachly::configure');
        }
        self::$adapters->register($name, $constructor);
    }

    /**
     * Configure session adapter
     *
     * @see SessionAdapter
     */
    public static function configureSessionAdapter(callable|AbstractAdapter $constructor): void
    {
        self::configureAdapter(static::SESS, $constructor);
    }

    /**
     * Configure redis adapter
     *
     * @see https://symfony.com/doc/current/components/cache/adapters/redis_adapter.html
     */
    public static function configureRedisAdapter(array|callable|RedisAdapter|RedisAdapterOptions $options): void
    {
        if(is_array($options)) {
            $options = new RedisAdapterOptions($options);
        }
        if($options instanceof RedisAdapterOptions) {
            $constructor = static function($namespace) use ($options) {
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
     * @see https://symfony.com/doc/current/components/cache/adapters/memcached_adapter.html
     */
    public static function configureMemcachedAdapter(array|callable|MemcachedAdapter|MemcachedAdapterOptions $options): void
    {
        if(is_array($options)) {
            $options = new MemcachedAdapterOptions($options);
        }
        if($options instanceof MemcachedAdapterOptions) {
            $constructor = static function($namespace) use ($options) {
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
     * @see https://symfony.com/doc/current/components/cache/adapters/pdo_doctrine_dbal_adapter.html
     */
    public static function configureDbAdapter(array|callable|PdoAdapter|DbAdapterOptions $options): void
    {
        if(is_array($options)) {
            $options = new DbAdapterOptions($options);
        }
        if($options instanceof DbAdapterOptions) {
            $constructor = static function($namespace) use ($options) {
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
     * @see https://symfony.com/doc/current/components/cache/adapters/filesystem_adapter.html
     */
    public static function configureFileSystemAdapter(array|callable|FilesystemAdapter|FileSystemAdapterOptions $options): void
    {
        if(is_array($options)) {
            $options = new FileSystemAdapterOptions($options);
        }
        if($options instanceof FileSystemAdapterOptions) {
            $constructor = static function($namespace) use ($options) {
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
     * @param string $namespace
     * @return CacheInstance
     */
    public static function db(string $namespace = self::DEFAULT_INSTANCE_NAME): CacheInstance
    {
        return self::instance($namespace, self::DB);
    }

    /**
     * Create cache instance with file adapter
     *
     * @param string $namespace
     * @return CacheInstance
     */
    public static function file(string $namespace = self::DEFAULT_INSTANCE_NAME): CacheInstance
    {
        return self::instance($namespace, self::FILE);
    }

    /**
     * Create cache instance with memory adapter
     * Uses $options['memAdapter'] as adapter
     *
     * @param string $namespace
     * @return CacheInstance
     */
    public static function mem(string $namespace = self::DEFAULT_INSTANCE_NAME): CacheInstance
    {
        return self::instance($namespace, self::MEM);
    }

    /**
     * Create cache instance with redis adap
     *
     * @param string $namespace
     * @return CacheInstance
     */
    public static function redis(string $namespace = self::DEFAULT_INSTANCE_NAME): CacheInstance
    {
        return self::instance($namespace, self::REDIS);
    }

    /**
     * Create cache instance with memCached adapter
     *
     * @param string $namespace
     * @return CacheInstance
     */
    public static function memCached(string $namespace = self::DEFAULT_INSTANCE_NAME): CacheInstance
    {
        return self::instance($namespace, self::MEM_CACHED);
    }

    /**
     * Create cache instance with session adapter
     *
     * @param string $namespace
     * @return CacheInstance
     */
    public static function sess(string $namespace = self::DEFAULT_INSTANCE_NAME): CacheInstance
    {
        return self::instance($namespace, self::SESS);
    }

    /**
     * Create cache instance
     *
     * @param string $namespace
     * @param string|null $adapterName - if null $options['defaultAdapter'] will be used
     * @return CacheInstance
     */
    public static function instance(string $namespace = self::DEFAULT_INSTANCE_NAME, string $adapterName = null): CacheInstance
    {
        $adapterName = $adapterName ?: static::getOpt('defaultAdapter');
        $namespace .= '-' . static::getDefaultInstanceName();

        return Helpers::once(
            'instance', $adapterName, $namespace,
            static function() use ($namespace, $adapterName) {
                if(!self::$adapters) {
                    throw new InvalidArgumentException('not initialized use Cachly::configure');
                }

                return new CacheInstance($namespace, $adapterName, self::$adapters->get($adapterName, $namespace));
            });
    }
    ####################### Start of helpers
}