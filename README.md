# Cachly

Quick shortcuts to [Symfony](https://github.com/symfony/cache) caching solution  <br>
It is released under the [MIT licence](https://github.com/infira/Cachly/blob/master/LICENCE.md). Drivers:

* Database (PDO)
* FileSystem
* [Memcached](https://www.php.net/manual/en/book.memcached.php)
* [Redis](https://github.com/phpredis/phpredis)
* Session

You can comment, offer changes, otherwise contribute [here on GitHub](https://github.com/infira/Cachly/issues), or ask questions to gen@infira.ee

# Table of contents

1. [Installing/Configuring]
    * [Basic configure](#basic-configure)
        * [Configure PDO/database adapter adapter](#configure-pdodatabase-adapter-adapter)
        * [Configure session adapter adapter](#configure-session-adapter-adapter)
        * [Configure filesystem adapter](#configure-filesystem-adapter)
        * [Configure memory adapter](#configure-memory-adapter)
            * [Configure memcached adapter](#set-default-memory-adapter)
            * [Configure redis adapter](#configure-redis-adapter)
            * [Set default memory adapter ](#set-default-memory-adapter)
        * [Configure your own adapter (basic)](#configure-your-own-adapter-basic)
        * [Configure your own adapter (with shortcuts)](#configure-your-own-adapter-with-shortcuts)
2. [Examples](#examples)
    * [Basic](#default-adapter-with-default-instance)
    * [New instance](#using-a-new-instance-for-default-adapter)
    * [Adapter instance shortcuts](#adapter-instance-shortcuts)

# Installing/Configuring

## Basic configure

```bash
$ composer require infira/cachly
```

```php
require_once 'vendor/autoload.php';

use Infira\Cachly\Cachly;

Cachly::configure([
    'defaultAdapter' => Cachly::SESS,
    'memAdapter' => Cachly::REDIS,
    'defaultInstanceName' => 'cachly-production',
    'cacheIDHashAlgorithm' => 'crc32b'
]);
```

**Options**

* `defaultAdapter` - adapter which will be used when accessing Cachly::$instanceMethod(...), ex. `Cachly::getValue('myCacheItem')`
* `memAdapter` - adapter which will be used when accessing Cachly::mem()->$instanceMethod, ex. `Cachly::mem()->getValue('myCacheItem')`
* `defaultInstanceName` - string which will be used to generate cacheIDS
* `cacheIDHashAlgorithm` - hash Algorithm used in making cacheID's
* `Cachly::setPropertyInstances` - for accessing Cachly::$sess instance

## Configure PDO/database adapter adapter

```php
Cachly::configureDbAdapter([
    'dsn' => 'mysql:host=mysql.docker;dbname=cachly',
    'table' => 'cachly_cache2',
    'user' => 'root',
    'password' => 'parool',
]);
```

## Configure session adapter adapter

```php
Cachly::configureSessionAdapter(static function($namespace) {
    Session::init();

    return new \Infira\Cachly\Adapter\SessionAdapter($namespace);
});
```

## Configure filesystem adapter

```php
Cachly::configureFileSystemAdapter([
    'directory' => __DIR__ . '/fileCache'
]);
```

## Configure memory adapter

### Configure memcached adapter

```php
Cachly::configureMemcachedAdapter([
    'host' => 'memcached://my.server.com:11211',
    'options' => [
        //... see https://symfony.com/doc/current/components/cache/adapters/memcached_adapter.html#configure-the-options
    ]
]);
```

### Configure redis adapter

```php
Cachly::configureRedisAdapter([
    'host' => 'redis://localhost:6379',
    'options' => [
        //... see https://symfony.com/doc/current/components/cache/adapters/redis_adapter.html#configure-the-options
    ]
]);
```

### Set default memory adapter

```php
Cachly::configure([
     //...
    'memAdapter' => Cachly::REDIS
]);
Cachly::setPropertyInstances([
    Cachly::MEM => static fn() => Cachly::mem(),
]);
//now you can access
Cachly::$sess->put(...)
//or
Cachly::sess('new instance')->put(...)
```

## Configure your own adapter (basic)

@see https://symfony.com/doc/current/components/cache.html for more information

```php
Cachly::configureAdapter(
    'myAdapter', function(string $namespace) {
    return MyAwesomeAdapter($namespace);
});
//accessing instance
Cachly::instance('cachly','myAdapter')->set('myKey','myValue');
```

## Configure your own adapter (with shortcuts)

```php
class MyCachly extends Cachly
{
    public static \Infira\Cachly\CacheInstance $myAdapter;

    public static function configure(array $options = []): void
    {
        parent::configure($options);
        Cachly::configureAdapter(
            'myAdapter', function(string $namespace) {
            return MyAwesomeAdapter($namespace);
        });
        static::$myAdapter = static::myAdapter(static::DEFAULT_INSTANCE_NAME);
    }

    public static function myAdapter(string $namespace = 'some-other-namespace'): \Infira\Cachly\CacheInstance
    {
        return static::instance($namespace, 'myAdapter')
    }
}
//now you can use
MyCachly::configure();
MyCachly::$myAdapter->set('myKey', 'value'); 
//or creating new instance 
MyCachly::myAdapter('new instance')->set('myKey', 'value');
```

### Shortcuts

```php
Cachly::setPropertyInstances([
Cachly::SESS => static fn() => Cachly::sess(),
Cachly::DB => static fn() => Cachly::db(),
Cachly::FILE => static fn() => Cachly::file(),
]);
$Cachly::$sess->put(...);
```

# Examples

## Default adapter with default instance

Once default adapter is configured you can use default methods for caching.

```php
Cachly::put('myKey', 'my Value', '+1 day');

if (Cachly::has('myKey'))
{
	//yei, still exists
	echo Cachly::getValue('myKey');
	Cachly::forgetValue('myKey');
}

/**
* @see https://symfony.com/doc/current/components/cache.html#basic-usage-psr-6
 */
Cachly::get('my_cache_key', function (\Infira\Cachly\Item\CacheItem $item) {
    $item->expiresAfter(3600);

    // ... do some HTTP request or heavy computations
    $computedValue = 'foobar';

    return $computedValue;
});

//add as many variables as you want as long last variable is callback
Cachly::once('key1',$filters,$someOtherVariable, function (\Infira\Cachly\Item\CacheItem $item) {
    $item->expiresAfter(3600);

    // ... do some HTTP request or heavy computations
    $computedValue = 'foobar';

    return $computedValue;
});

//you can use also collections
$MyCollection = Cachly::sub('myCollectionName');
$MyCollection->put('myKey1', 'value1');
$MyCollection->put('myKey2', 'value2');
$MyCollection->put('myKey3', 'value3');
$MyCollectionSub = $MyCollection::sub('subCollection');
$MyCollectionSub->put('myKey1', 'value1');
$MyCollectionSub->put('myKey2', 'value2');
$MyCollectionSub->put('myKey3', 'value3');
$MyCollection->getValue('myKey3'); //outputs value3
$MyCollection->all(); //outputs
/*
Array
(
    [myKey1] => value
    [myKey2] => value
    [myKey3] => value
)
*/
$MyCollection::sub('subCollection')->getValue('myKey3'); //outputs value3
$MyCollectionSub->all(); //outputs
/*
Array
(
    [myKey1] => value
    [myKey2] => value
    [myKey3] => value
)
*/
```

## Using a new instance for default adapter

```php
Cachly::instance('newInstance')->put('key1', 'key1 value');
Cachly::instance('newInstance')->all(); //outputs

/*
Array
(
    [key1] => key1 value
)
*/
```

## Adapter instance shortcuts

* yourOwnShortcut - see how to make own [shortcuts](#configure-your-own-adapter-with-shortcuts)

```php
Cachly::sess('mySessionInstance')->put('key1', 'key1 value');
Cachly::sess('mySessionInstance')->all();
Cachly::$sess->put('key1', 'key1 value');
Cachly::$sess->all();

Cachly::mem('mySessionInstance')->put('key1', 'key1 value');
Cachly::mem('mySessionInstance')->all();
Cachly::$mem->put('key1', 'key1 value');
Cachly::$mem->all();
....
```
