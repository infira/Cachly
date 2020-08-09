<?php
require_once "../vendor/autoload.php";

class Sess extends Infira\Utils\Session
{

}

use Infira\Utils\File;

if (defined("TESTING_CODE_PERFORMANCE"))
{
	require_once "../src/CacherProfiler.php";
	
	$class   = trim(File::getContent("../src/Cachly.php"));
	$class   = str_replace('Cacher', 'CacherProfiler', $class);
	$tmpFile = 'tmp/Cachly.php';
	File::create($tmpFile, $class);
	require_once $tmpFile;
}
else
{
	require_once "../src/Cacher.php";
	require_once "../src/Cachly.php";
}


use Infira\Cachly\Cachly;
use Infira\Utils\Http;

error_reporting(E_ALL);
require "../src/driver/RuntimeMemory.php";
require "../src/driver/Session.php";
require "../src/driver/Redis.php";
require "../src/driver/Memcached.php";
require "../src/driver/File.php";
require "../src/driver/Db.php";


function convert($size)
{
	$unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
	
	return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
}

Sess::init();
Cachly::configRedis(['host' => 'localhost', 'port' => 11211, 'fallbackDriver' => Cachly::SESS]);
Cachly::configureMemcached(['host' => 'localhost', 'port' => 11211, 'fallbackDriver' => Cachly::SESS]);
Cachly::configureDb(['user' => 'vagrant', 'password' => 'parool', 'db' => 'kis', 'table' => 'cachly_cache', 'host' => 'localhost', 'afterConnect' => null, 'fallbackDriver' => Cachly::SESS]);
Cachly::configureFile(__DIR__ . '/fileCache', null);


$drivers = [Cachly::DB, Cachly::FILE, Cachly::MEM, Cachly::REDIS, Cachly::RUNTIME_MEMORY, Cachly::SESS];
if (Http::existsGET('single'))
{
	$drivers = [Http::get('single')];
}