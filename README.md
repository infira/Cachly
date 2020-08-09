# Cachly

Comprehensive cachly library provides an unified cache drivers for caching key-value data. It is released under the [MIT licence](https://github.com/infira/Cachly/blob/master/LICENCE.md).
Drivers:
* Database (mysql)
* File
* [Memcached](https://www.php.net/manual/en/book.memcached.php)
* [Redis](https://github.com/phpredis/phpredis)
* RuntimeMemory
* Session

You can comment, offer changes, otherwise contribute [here on github](https://github.com/infira/Cachly/issues), or ask questions to gen@infira.ee

# Table of contents
1. [Installing/Configuring](#installingconfiguring)
    * [Installation](#installation)
    * [Configure](#configure)
        * [Set default driver](#set-default-driver)
        * [Configure redis driver](#configure-redis-driver)
        * [Configure memcahced driver](#configure-memcached-driver)
        * [Configure database driver](#configure-database-driver)
1. [Customizing or making your own driver](#customizing-or-making-your-own-driver)
1. [Examples](#usage-examples)
    * [Default](#default-driver-and-default-instance-usage)
    * [New instance](#using-a-new-instance-for-default-driver)
    * [Driver shortcuts](#using-a-different-driver-instance)
1. [Flushing and garbage collection](#flushing-and-garbace-collection)
1. [Cachly:: methods](#cachly-methods)
1. [Driver instance methods](#instance-methods)
1. [Tips and tricks](#tips-and-tricks)
    * [Keys & once](#key-and-once-usage)
    * [Cool trick (once)](#once)
    * [Fallback driver](#fallback-driver)

-----

# Installing/Configuring

## Installation

Use [composer](http://getcomposer.org) to install the library:

Add the library to your `composer.json` file in your project:

```javascript
{
  "require": {
      "infira/cachly": "*"
  }
}
```
if u want to use latest and greatest
```javascript
{
  "require": {
      "infira/cachly": "dev-master"
  }
}
```
or terminal

```bash
$ composer require infira/cachly
```

## Configure
You have the option to use several drives at once or only one. Its up your needs really.
### NB!
* if ```$options['fallbackDriver']``` is NULL then in case of error \Error is thrown. Read more about [fallback drivrers](#fallback-driver)
* ```$options['afterConnect']```  - is optional

### Set default driver
```php
require_once "vendor/autoload.php";

use Infira\Cachly\Cachly;

Cachly::init();
Cachly::setDefaultDriver(Cachly::REDIS);
```

### Configure redis driver

#### Use builtin connector
Example below Cachly will attempt to make connection to redis server
```php
$options = [];
$options['password'] = 'mypassword';
$options['host'] = 'localhost';
$options['port'] = 6379;
/**
 * if you pass callable then it will be called after successful redis connection is made and Redis object will be passed to function
 *
 * @param Redis $Redis
 * @see https://github.com/phpredis/phpredis
 */
$options['afterConnect']   = function ($Redis)
{
	//do something with redis connection
};
$options['fallbackDriver'] = Cachly::SESS;
Cachly::configRedis($options);
```
#### Provide your own connection
```php
$Redis = new Redis();
$Redis->pconnect('redisHost', 'redisPort');
$Redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
if (!$Redis->auth('redisPassword'))
{
	throw new Error('Redis connection authentication failed');
}
Cachly::configureRedis($Redis);
```

### Configure memcached driver

#### Use builtin connector
```php
$options = [];
$options['host'] = 'localhost';
$options['port'] = 11211;
/**
 * if you pass callable then it will be called after successful memcached connection is made and Memcached object will be passed to function
 *
 * @param Memcached $Memcached
 * @see https://www.php.net/manual/en/book.memcached.php
 */
$options['afterConnect']   = function ($Memcached) //
{
	//	//do something with memcached connection
};
$options['fallbackDriver'] = Cachly::SESS;
Cachly::configureMemcached($options);
```
#### Provide your own connection
```php
$Memcached = new Memcached();
$Memcached->addServer('memcachedHost', 12111);
Cachly::configureMemcached($Memcached);
```

### Configure database driver
Db must have table for caching [table](https://github.com/infira/Cachly/blob/master/cachly_db_table.sql)
If you want more pefromance use [ENGINE=MEMORY](https://mariadb.com/kb/en/memory-storage-engine/)

#### Use builtin connector
```php
$options = [];
$options['user'] = 'myusername';
$options['password'] = 'mypassword';
$options['host'] = 'localhost';
$options['port'] = ''; //Leave port empty to use system configured mysql port (ini_get("mysqli.default_port"))
$options['db'] = 'myDbName'; 
$options['table'] = 'cachly_data'; 
/**
 * if you pass callable then it will be called after successful redis connection is made and Redis object will be passed to function
 *
 * @param mysqli $mysqli
 * @see https://www.php.net/manual/en/book.mysqli.php
 */
$options['afterConnect']   = function ($mysqli)
{
	//do something with mysqli connection
};
Cachly::configureDb($options);
```
#### Provide your own connection
```php
$mysqli = new \mysqli('dbHost', 'dbUser', 'dbPass', 'dbName');
Cachly::configureDb($mysqli);
```

### Configure file driver
```php
Cachly::configureFile('fullPath to caching folder','fallbackDriver');
```

# Customizing or making your own driver

```php
class myAwesomeDriverActualClass extends \Infira\Cachly\DriverHelper
{
	public function __construct()
	{
		if (errorOccurs()) //if you want on internal error you can use fallback driver
		{
			$this->setFallbackDriver("driver");
		}
		parent::__construct("myAwesomeDriver");
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doSet(string $CID, $data, int $expires = 0): bool
	{
		// TODO: Implement doSet() method.
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doExists(string $CID): bool
	{
		// TODO: Implement doExists() method.
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGet(string $CID)
	{
		// TODO: Implement doGet() method.
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doDelete(string $CID): bool
	{
		// TODO: Implement doDelete() method.
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGetItems(): array
	{
		// TODO: Implement doGetItems() method.
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doFlush(): bool
	{
		// TODO: Implement doFlush() method.
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGc(): bool
	{
		// TODO: Implement doGc() method.
	}
}
Cachly::$Driver->add('myAwesomeDriver','Awesom', function ()
{
	return new myAwesomeDriverActualClass();
});
Cachly::setDefaultDriver('myAwesomeDriver');

Cachly::$Driver->add('myCoolDriver', 'Cool', function ()
{
	return new myCoolDriver();
});

Cachly::set("myKey", "value"); //uses myAwesomeDriver

Cachly::di("myCoolDriver")->set("myKey", "value"); //uses myCoolDriver

```

### Adding custom shortcuts
```php
class Cachly extends \Infira\Cachly\Cachly
{
	/**
	 * My cool driver
	 */
	public static function cool(string $instance = 'cachly'): \Infira\Cachly\Cacher
	{
		return self::di("myCoolDriver", $instance);
	}
}
//now instead of Cachly::di("myCoolDriver")->set("myKey", "value"); you can use
Cachly::cool()->set("myKey", "value");
```

# Usage examples 

## Default driver and default instance usage 

Once default driver is configured you can use default methods for caching.
Default driver vad defined earlier. 
See [more features](#methods)

```php
Cachly::set("myKey", "my Value", "+1 day");

if (Cachly::exists('myKey'))
{
	//yei its still exists
	echo Cachly::get('myKey');
	Cachly::delete('myKey');
}

//you can use also collections
$MyCollection = Cachly::Collection('myCollectionName');
$MyCollection->set('myKey1', 'value1');
$MyCollection->set('myKey2', 'value2');
$MyCollection->set('myKey3', 'value3');
$MyCollectionSub = $MyCollection->Collection("subCollection");
$MyCollectionSub->set('myKey1', 'value1');
$MyCollectionSub->set('myKey2', 'value2');
$MyCollectionSub->set('myKey3', 'value3');
$MyCollection->get("myKey3"); //outputs value3
$MyCollection->debug(); //outputs
/*
Array
(
    [myKey1] => value
    [myKey2] => value
    [myKey3] => value
)
*/
$MyCollection->Collection("subCollection")->get("myKey3"); //outputs value3
$MyCollectionSub->debug(); //outputs
/*
Array
(
    [myKey1] => value
    [myKey2] => value
    [myKey3] => value
)
*/
```

## Using a new instance for default driver

```php
Cachly::instance('newInstance')->set("key1", "key1 value");
Cachly::instance('newInstance')->debug(); //outputs

/*
Array
(
    [key1] => key1 value
)
*/
```

## Using a different driver instance
* [db](#db)
* [file](#file)
* [mem](#mem)
* [redis](#redis)
* [rm](#rm)
* [sess](#sess)
* yourOwnShortcut - see how to make own [shortcuts](#adding-custom-shortcuts)
```php
Cachly::sess('mySessionInstance')->set("key1", "key1 value");
Cachly::sess('mySessionInstance')->debug();
```

# Flushing and garbace collection
## Garbage collction
Redis and memcached will do their own garbage collection by server side
To initiate garbage collection manually
```php
Cachly::$Driver->DriverName->gc();
```
## Flush drivers all data
These methods flushes all data (including all instances) inside the driver
### Flushing built in drivers
```php
Cachly::$Driver->DriverName->flush();
Cachly::$Driver->Cool->flush(); //flushes "myCoolDriver" driver data which
```
### Flushing custom drivers
```php
Cachly::$Driver->Awesom->flush(); //flushes "myAwesomeDriver" driver data which
```

## Flushing instance data
```php
Cachly::flush();                                                     //flushes default driver default instance data
Cachly::instance("myInstance")->flush();                             //flushes default driver "myInstance" instance data, including collections
Cachly::Collection("myCollection")->flush();                         //flushes default driver default instance "myCollection" collection data
Cachly::instance("myInstance")->Collection("myCollection")->flush(); //flushes default driver "myInstance" instance "myCollection" collection data
Cachly::sess("myInstance")->Collection("myCollection")->flush();     //flushes session driver "myInstance" "myCollection" collection data
```

# Cachly methods

| Name | Description |
|------|-------------|
|[setDefaultDriver](#setdefaultdriver)|Set default driver|
|[setHashingAlgorithm](#sethashingalgorithm)|Set default driver|
|[configRedis](#configredis)|Configure redis driver (creates own client)|
|[configureDb](#configuredb)|Configure database driver (creates own client)|
|[configureFile](#configurefile)|Configure file driver|
|[configureMemcached](#configurememcached)|Configure memcached driver (creates own client)|

## Driver shortcuts
|[instance](#instance)|Shortcut to default driver instance cacher|
|[db](#db)|Shortcut to builtin database driver cacher|
|[file](#file)|Shortcut to builtin file driver cacher|
|[mem](#mem)|Shortcut to builtin memcachec driver cacher|
|[redis](#redis)|Shortcut to builtin redis driver cacher|
|[rm](#rm)|Shortcut to builtin runtimememory driver cacher|
|[sess](#sess)|Shortcut to builtin session driver cacher|
|[di](#di)|Shortcut to driver cacher instance by name|

## Configuring

### setDefaultDriver  

**Description**

```php
public static setDefaultDriver (string $name)
```

Set default driver 

 

**Parameters**

* `(string) $name`

**Return Values**

`void`


<hr />

### setHashingAlgorithm  

**Description**

```php
final public static setHashingAlgorithm (string $name)
```

Set hashing algorithm 

 

**Parameters**

* `(string) $name`
: crc32,md5 or sha1(default) See - https://stackoverflow.com/questions/3665247/fastest-hash-for-non-cryptographic-uses/5021846

**Return Values**

`void`




<hr />

### configRedis  

**Description**

```php
final public static configRedis (array|\Redis $redis)
```

Configure redis driver 

 

**Parameters**

* `(array|\Redis) $redis`
: - \Redis class or options array ['password'=>'', 'host'=>'localhost', 'port'=>6379, 'afterConnect'=>null|callable, 'fallbackDriver'=>null|string]  

**Return Values**

`void`


<hr />

### configureDb  

**Description**

```php
final public static configureDb (array|\mysqli $db)
```

Configure database driver 

 

**Parameters**

* `(array|\mysqli) $db`
: - \mysqli class or options array ['user'=>'', 'password'=>'', 'host'=>'localhost', 'port'=>'ini_get("mysqli.default_port")', 'db'=>'myDbName', 'table'=>'cachly_data', 'afterConnect'=>null|callable, 'fallbackDriver'=>null|string] <br />  
Leave port empty to use system configured mysql port (ini_get("mysqli.default_port"))  

**Return Values**

`void`


<hr />

### configureFile

**Description**

```php
final public static configureFile (string $path, string $fallbackDriver)
```

Configure file driver 

 

**Parameters**

* `(string) $path`
* `(string) $fallbackDriver`
: - in case of redis connection error use fallback driver  

**Return Values**

`void`


<hr />

### configureMemcached  

**Description**

```php
final public static configureMemcached (array|\Memcached $memcached)
```

Configure memcached driver 

 

**Parameters**

* `(array|\Memcached) $memcached`
: - \Memcached class or options array ['host'=>'localhost', 'port'=>11211, 'afterConnect'=>null|callable, 'fallbackDriver'=>null|string]  

**Return Values**

`void`


<hr />

## Driver isntance shortcuts

### instance  

**Description**

```php
public static instance (string $instance = 'cachly')
```

Get default driver instance driver 

 

**Parameters**

* `(string) $instance`

**Return Values**

`\Infira\Cachly\Cacher`




<hr />

### db  

**Description**

```php
public static db (string $instance = 'cachly')
```

Shortcut to builtin database driver cacher

 

**Parameters**

* `(string) $instance`

**Return Values**

`\Infira\Cachly\Cacher`




<hr />

### file  

**Description**

```php
public static file (string $instance = 'cachly')
```

Shortcut to builtin file driver cacher

 

**Parameters**

* `(string) $instance`

**Return Values**

`\Infira\Cachly\Cacher`




<hr />

### mem  

**Description**

```php
public static mem (string $instance = 'cachly')
```

Shortcut to builtin memcached driver cacher

 

**Parameters**

* `(string) $instance`

**Return Values**

`\Infira\Cachly\Cacher`




<hr />

### redis  

**Description**

```php
public static redis (string $instance = 'cachly')
```

Shortcut to builtin redis driver cacher

 

**Parameters**

* `(string) $instance`

**Return Values**

`\Infira\Cachly\Cacher`




<hr />

### rm  

**Description**

```php
public static rm (string $instance = 'cachly')
```

Shortcut to builtin runtimememory driver cacher

 

**Parameters**

* `(string) $instance`

**Return Values**

`\Infira\Cachly\Cacher`




<hr />

### sess  

**Description**

```php
public static sess (string $instance = 'cachly')
```

Shortcut to builtin session driver cacher

 

**Parameters**

* `(string) $instance`

**Return Values**

`\Infira\Cachly\Cacher`

<hr />

### di  

**Description**

```php
public static di (string $driver, string $instance = 'cachly')
```

Shortcut to driver cacher instance by name

 

**Parameters**

* `(string) $driver` - driver name
* `(string) $instance` - instance name

**Return Values**

`\Infira\Cachly\Cacher`

<hr />

# Instance methods

| Name | Description |
|------|-------------|
|[Collection](#collection)|Makes a cache collection|
|[debug](#debug)|Dumps current instance/collection items|
|[delete](#delete)|Delete cache item|
|[deleteRegex](#deleteregex)|Delete by regular expression|
|[deletedExpired](#deletedexpired)|Delete expired items from current instance/collection|
|[each](#each)|Loops all items and and call $callback for every item<br />$callback($value,$cacheKey)|
|[eachCollection](#eachcollection)|Call $callback for every collection<br />$callback($Colleciton,$collectionName)|
|[exists](#exists)|Does cache item exists|
|[expiresAt](#expiresat)|Tells when cache item expires|
|[flush](#flush)|Flush data on current instance/collection|
|[get](#get)|Get cache item|
|[getDriver](#getdriver)|Get current driver|
|[getCollections](#getcollections)|Get all current collections|
|[getIDKeyPairs](#getidkeypairs)|Get instance/collection cacheKey/cacheID pairs|
|[getIDS](#getids)|Get cache IDS|
|[getItems](#getitems)|Get all current instance/collection or collection cache items|
|[getKeys](#getkeys)|Get cache keys|
|[getMulti](#getmulti)|Get multiple items by keys|
|[getRegex](#getregex)|Get cache items by regular expression|
|[isExpired](#isexpired)|Is cache item expired|
|[key](#key)|Set key for further use|
|[once](#once)|Call $callback once per $key existence
All arguments after  $callback will be passed to callable method|
|[onceExpire](#onceexpire)|Call $callback once per $key existence or when its expired
All arguments after  $forceSet will be passed to callable method|
|[onceForce](#onceforce)|Call $callback once per $key existence or force it to call
All arguments after  $forceSet will be passed to callable method|
|[set](#set)|Set cache value|




### Collection  

**Description**

```php
public Collection (string $key)
```

Makes a cache collection 

 

**Parameters**

* `(string) $key`

**Return Values**

`\Driver`




### debug  

**Description**

```php
public debug (void)
```

Dumps current instance/collection items 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`




<hr />


### delete  

**Description**

```php
public delete (string $key = '')
```

Delete cache item 

 

**Parameters**

* `(string) $key` - cache key, if is empty then try to use key setted by key() method

**Return Values**

`bool`




<hr />


### deleteRegex  

**Description**

```php
public deleteRegex (string $pattern)
```

Delete by regular expression 

 

**Parameters**

* `(string) $pattern`

**Return Values**

`bool`




<hr />


### deletedExpired  

**Description**

```php
public deletedExpired (void)
```

Delete expired items from current instance/collection 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`bool`




<hr />


### each  

**Description**

```php
public each (callable $callback)
```

Loots all items and and call $callback for every item<br />$callback($value,$cacheKey) 

 

**Parameters**

* `(callable) $callback`

**Return Values**

`void`




<hr />


### eachCollection  

**Description**

```php
public eachCollection (callable $callback)
```

Call $callback for every collection<br />$callback($Colleciton,$collectionName) 

 

**Parameters**

* `(callable) $callback`

**Return Values**

`void`




<hr />


### exists  

**Description**

```php
public exists (string $key = '')
```

Does cache item exists 

 

**Parameters**

* `(string) $key` - cache key, if is empty then try to use key setted by key() method

**Return Values**

`bool`




<hr />


### expiresAt  

**Description**

```php
public expiresAt (string $key = '')
```

Tells when cache item expires 

 

**Parameters**

* `(string) $key` - cache key, if is empty then try to use key setted by key() method

**Return Values**

`string|null|int`

> - "expired","never" or timestamp when will be expired, returns null when not exists


<hr />


### flush  

**Description**

```php
public flush (void)
```

Flush data on current instance/collection 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`bool`




<hr />


### get  

**Description**

```php
public get (string $key, mixed $returnOnNotExists)
```

Get cache item 

 

**Parameters**

* `(string) $key` - cache key, if is empty then try to use key setted by key() method
* `(mixed) $returnOnNotExists`
: - return that when item is not found  

**Return Values**

`mixed`




<hr />


### getDriver  

**Description**

```php
public getDriver (void)
```

Get current driver 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`\Infira\Cachly\DriverHelper`




<hr />


### getCollections  

**Description**

```php
public getCollections (void)
```

Get all current collections 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`




<hr />


### getIDKeyPairs  

**Description**

```php
public getIDKeyPairs (void)
```

Get instance/collection cacheKey/cacheID pairs 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`




<hr />


### getIDS  

**Description**

```php
public getIDS (void)
```

Get cache IDS 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`




<hr />


### getItems  

**Description**

```php
public getItems (void)
```

Get all current instance/collection or collection cache items 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`




<hr />


### getKeys  

**Description**

```php
public getKeys (void)
```

Get cache keys 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`




<hr />


### getMulti  

**Description**

```php
public getMulti (array $keys)
```

Get multiple items by keys 

 

**Parameters**

* `(array) $keys`
: - for examples ['key1','key2]  

**Return Values**

`array`

> - ['key1'=>'value1', 'key2'=>'value']


<hr />


### getRegex  

**Description**

```php
public getRegex (string $pattern)
```

Get cache items by regular expression 

 

**Parameters**

* `(string) $pattern`

**Return Values**

`array`




<hr />


### isExpired  

**Description**

```php
public isExpired (string $key = '')
```

Is cache item expired 

 

**Parameters**

* `(string) $key` - cache key, if is empty then try to use key setted by key() method

**Return Values**

`bool`




<hr />


### key  

**Description**

```php
public key (string|array|int $key = '')
```

Set key for further use 
Its a old way of [once](#once)

 

**Parameters**

* `(string|array|int) $key`

**Return Values**

`$this`

#### Example
I reccoomend to use [once](#once) but nonetheless it exists for backward compatibility
```php
function getMyStuff($arg1, $arg2, $arg3)
{
    $Collection = Cachly::key("my very long cache key", func_get_args());
    if (!$Collection->exists())
    {
        //get stuff and save it
        //
        $Collection->set('', "stuff");
    }
    
    return $Collection->get('');
}
function getMyStuffCollection($arg1, $arg2, $arg3)
{
    $Collection = Cachly::Collection("getMyStuff")->key("my very long cache key", func_get_args());
    if (!$Collection->exists())
    {
        //get stuff and save it
        //
        $Collection->set('', "stuff");
    }
    
    return $Collection->get('');
}
```

<hr />


### once  

**Description**

```php
public once (mixed $key, string|array|int $callback, mixed $callbackArg1, mixed $callbackArg2, mixed $callbackArg3, mixed $callbackArg_n)
```

Call $callback once per $key existence
All arguments after  $callback will be passed to callable method 

 

**Parameters**

* `(mixed) $key` - cache key, if is empty then try to use key setted by key() method
* `(string|array|int) $callback`
: method result will be setted to memory for later use  
* `(mixed) $callbackArg1`
: - this will pass to $callback as argument1  
* `(mixed) $callbackArg2`
: - this will pass to $callback as argument2  
* `(mixed) $callbackArg3`
: - this will pass to $callback as argument3  
* `(mixed) $callbackArg_n`
: - ....  

**Return Values**

`mixed`

> - $callback result

#### Example
Instade of doing this
```php
function getMyStuff()
{
	if (!Cachly::exists("myCache"))
	{
		$myStuff = ['getMyStuff from DB for example'];
		
		Cachly::set("myCache", $myStuff);
	}
	
	return Cachly::get("myCache");
}
```
you can do it Did you more beautifully
```php
function getMyStuff()
{
    return Cachly::once("myCache", function ()
    {
        $myStuff = ['getMyStuff from DB for example'];
        
        return $myStuff;
    });
}
```
Look at more [comprihensive example](#key-and-once-usage)
<hr />


### onceExpire  

**Description**

```php
public onceExpire (string|array|int $key, callable $callback, int|string $expires, mixed $callbackArg1, mixed $callbackArg2, mixed $callbackArg3, mixed $callbackArg_n)
```

Call $callback once per $key existence or when its expired
All arguments after  $forceSet will be passed to callable method 

 

**Parameters**

* `(string|array|int) $key` - cache key, if is empty then try to use key setted by key() method
* `(callable) $callback`
* `(int|string) $expires`
: - when expires. (int)0 - forever,(string)"10 hours" -  will be converted to time using strtotime(), (int)1596885301 - will tell Driver when to expire. If $expires is in the past, it will be converted as forever  
* `(mixed) $callbackArg1`
: - this will pass to $callback as argument1  
* `(mixed) $callbackArg2`
: - this will pass to $callback as argument2  
* `(mixed) $callbackArg3`
: - this will pass to $callback as argument3  
* `(mixed) $callbackArg_n`
: - ....  

**Return Values**

`mixed|null`

> - $callback result

#### Example
It will lives 10 days. If when its expired the onceExpire method will call your geting method again.
```php
function getMyStuff()
{
    return Cachly::onceExpire("myCache","+10 days", function ()
    {
        $myStuff = ['getMyStuff from DB for example'];
        
        return $myStuff;
    });
}
```

<hr />


### onceForce  

**Description**

```php
public onceForce (string|array|int $key, callable $callback, bool $forceSet, mixed $callbackArg1, mixed $callbackArg2, mixed $callbackArg3, mixed $callbackArg_n)
```

Call $callback once per $key existence or force it to call
All arguments after  $forceSet will be passed to callable method 

 

**Parameters**

* `(string|array|int) $key`
* `(callable) $callback`
* `(bool) $forceSet`
: - if its true then $callback will be called regardless of is the $key setted or not  
* `(mixed) $callbackArg1`
: - this will pass to $callback as argument1  
* `(mixed) $callbackArg2`
: - this will pass to $callback as argument2  
* `(mixed) $callbackArg3`
: - this will pass to $callback as argument3  
* `(mixed) $callbackArg_n`
: - ....  

**Return Values**

`mixed|null`

> - $callback result


#### Example
Its the weird on to understad, i think the example below explains it

```php
function getMyStuff()
{
    $forsSet = isset($_GET['resetMyCache']) ? true : false;
    return Cachly::onceForce("myCache",$forsSet, function ()
    {
        $myStuff = ['getMyStuff from DB for example'];
        
        return $myStuff;
    });
}
```

<hr />


### set  

**Description**

```php
public set (string $key, mixed $value, int|string $expires)
```

Set cache value 

 

**Parameters**

* `(string) $key` - cache key, if is empty then try to use key setted by key() method
: -  cache key  - cache key, if is empty then try to use key setted by key() method
* `(mixed) $value`
: - value to store  
* `(int|string) $expires`
: - when expires. (int)0 - forever,(string)"10 hours" -  will be converted to time using strtotime(), (int)1596885301 - will tell Driver when to expire. If $expires is in the past, it will be converted as forever  

**Return Values**

`string`

> - returns cacheID which was used to save $value

#### Example
```php
Cachly::set("key","value","10 days");    // will expire in 10 days
Cachly::set("key","value","10");         // will expire in 10 seconds
Cachly::set("key","value","10 minutes"); // will expire in 10 seconds
```

<hr />

# Tips and tricks

## once() usage
Lets sey that you want to cache key depend on the values of the getMyStuff function arguments
```php
function getMyStuff($arg1, $arg2, $arg3)
{
    return Cachly::once(["my very long cache key", func_get_args()], function ()
    {
        return "stuff";
    });
}

function getMyStuffCollection($arg1, $arg2, $arg3)
{
    return Cachly::Collection("getMyStuff")->once(["my very long cache key", func_get_args()], function ()
    {
        return "stuff";
    });
}
```

## Fallback driver
When redis,memcached,db driver connection fails or some kind on other error then you can configure to dirver to fallback reliable driver.
In example below when connection ro redis server fails it fallbacks to built in session driver
```php
//....
$options['fallbackDriver'] = Cachly::SESS;
Cachly::configRedis($options);
```
or you can use fallback to own driver
```php
$options['fallbackDriver'] = 'myCoolDriver';
Cachly::configRedis($options);
```
Read more for [customizing drivers](#customizing-or-making-your-own-driver)