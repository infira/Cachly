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
]);
```

**Options**

* `defaultAdapter` - adapter which will be used when accessing Cachly::$instanceMethod(...)
* `memAdapter` - adapter which will be used when accessing Cachly::mem()->$instanceMethod
* `defaultInstanceName` - default namespace
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
Cachly::configureAdapter('myAdapter', function (string $namespace) {
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
        static::$myAdapter = static::myAdapterInstance();
    }

    public static function myAdapterInstance(string $namespace = null): \Infira\Cachly\CacheInstance
    {
        return static::instance($namespace, 'myAdapter')
    }
}
//now you can use
MyCachly::configure();
MyCachly::$myAdapter->set('myKey', 'value'); 
//or creating new instance 
MyCachly::myAdapterInstance('new instance')->set('myKey', 'value');
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

Choose your favorite

```php
use Infira\Cachly\CacheItem;

if (!Cachly::has('myKey')) {
    Cachly::put('myKey', 'my Value', '+1 day'); //save value immediately and will expire after one day
}

Cachly::get('key','defaultValue'); //defaultValue
Cachly::get('key'); //null
Cachly::put('key','stored value'); //CacheItem
Cachly::get('key','defaultValue'); //stored value
Cachly::get('key'); //stored value


//run callback when value does not exist
Cachly::get('compute-key','defaultValue'); //defaultValue
Cachly::get('compute-key'); //null
Cachly::get('compute-key', function (CacheItem $item) {
    $item->expiresAfter(3600);

    // do computations
    $computedValue = 'foobar';

    return $computedValue;
});
Cachly::get('compute-key','defaultValue'); //foobar
Cachly::get('compute-key'); //foobar

$item = Cachly::set('array', ['init value']);
$item[] = 'value2';
$item['key'] = 'value3';
Cachly::get('array'); //foobar
```

### Using method arguments as key

Use automated hashing from any value to compute value.

```php
function filterItems(DateTimeInterface $date, array $filters): mixed
{
    //add as many variables as you want as long last variable is callable
    return Cachly::once('getDataFromDataBase', $date, $filters, function (CacheItem $item) use ($date, $filters) {
        $item->expiresAfter(3600);

        return db()->where('date', $date)->where($filters);
    });
}
```

### Defer

```php
$item = Cachly::set('key','value1')->expire('tomorrow');
$item->set('value2')
Cachly::get('key'); //value2
Cachly::setMany(['key1'=> 'value1','key2' => 'value2']);
Cachly::commit(); //save all deferred items
Cachly::get('key'); //value2
```

### Save, commit, autoSave

```php
$item = Cachly::set('key2','value1')->expire('tomorrow')->save(); //saves value to database
$item->save(); //will not save because nothing has changed since last save
$item->commit(); //will save value without checking changes
$item->set('newValue')->save(); //will save
$item->set('newValue')->save(); //do nothing
$item->expire(5)->save(); //will save

$autoSave = Cachly::set('key2','value1')->autoSave(true); //tries save after every change
$autoSave->set('new value'); //will call save()
$autoSave->expire('tomorrow'); //will also call save

```

### Sub instances

```php
$sub = Cachly::sub('myCollectionName');
$sub->set('myKey1', 'value1')->expires('tomorrow');
$sub->set('myKey2', 'value2');
$sub->all(); //[]
$sub->commit(); //save values
$sub->all(); //['myKey1' => 'value1','myKey2' => 'value2']

$subOfSub = $sub::sub('subCollection');
$subOfSub->put('myKey1', 'value1');
$subOfSub->put('myKey2', 'value2');
$subOfSub->get('myKey2'); //outputs value2
$subOfSub->all(); //['myKey1' => 'value1','myKey2' => 'value2']
$sub::sub('subCollection')->get('myKey2'); //outputs value2
$subOfSub->all(); //['myKey1' => 'value1','myKey2' => 'value2']
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
