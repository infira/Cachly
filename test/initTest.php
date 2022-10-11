<?php

if (defined("TESTING_CODE_PERFORMANCE")) {
    require_once "../src/CacherProfiler.php";

    $class = trim(File::content("../src/Cachly.php"));
    $class = str_replace('Cacher', 'CacherProfiler', $class);
    $tmpFile = 'tmp/Cachly.php';
    File::put($tmpFile, $class);
    require_once $tmpFile;
}
else {
    require_once "../src/Cacher.php";
    require_once "../src/Cachly.php";
}


use Infira\Cachly\Cachly;
use Infira\Cachly\options\MemcachedDriverOptions;
use Infira\Cachly\options\RedisDriverOptions;
use Infira\Cachly\options\DbDriverOptions;
use Infira\Cachly\options\FileDriverOptions;
use Wolo\File\File;
use Wolo\Request\Http;
use Wolo\Request\Session;

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

Session::init();
$redisOptions = new RedisDriverOptions();
Cachly::configRedis($redisOptions);

$memcachedOptions = new MemcachedDriverOptions();
Cachly::configureMemcached($memcachedOptions);

$dbOptions = new DbDriverOptions();
$dbOptions->host = 'mysql.docker';
$dbOptions->user = 'root';
$dbOptions->password = 'parool';
$dbOptions->db = 'cachly';
$dbOptions->table = 'cachly_cache';
Cachly::configureDb($dbOptions);

$fileOptions = new FileDriverOptions();
$fileOptions->cachePath = __DIR__ . '/fileCache';
Cachly::configureFile($fileOptions);


$drivers = [
    Cachly::DB,
    Cachly::FILE,
//    Cachly::MEM,
    //Cachly::REDIS,
    Cachly::RUNTIME_MEMORY,
    Cachly::SESS
];
if (Http::existsGET('single')) {
    $drivers = [Http::get('single')];
}
Cachly::init();