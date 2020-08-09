<?php

use Infira\Cachly\Cachly;

define("TESTING_CODE_PERFORMANCE", true);
require "initTest.php";

function microtime_float()
{
	[$usec, $sec] = explode(" ", microtime());
	
	return ((float)$usec + (float)$sec);
}


//for ($i = 0; $i <= 10000; $i++)
//{
Cachly::setDefaultDriver(Cachly::MEM);
Cachly::set("cacheKey", "cacheValue");
Cachly::set("regularExpression10Key", "regular expression value");
Cachly::Collection("collection")->set("collection cacheKey1", "collection cache value");
Cachly::Collection("collection")->set("collection cacheKey2", "collection cache value");
Cachly::Collection("collection")->Collection("subCollection")->set("sub collection cacheKey", "collection cache value");
Cachly::get("cacheKey");
Cachly::Collection("collection")->getItems();
Cachly::getItems();
Cachly::Collection("collection")->Collection("subCollection")->getItems();
Cachly::set('expireable', 'expire value', '1 seconds');
Cachly::get('expireable', 'isExpired');
Cachly::deletedExpired();
Cachly::getItems();
Cachly::getRegex('/\d/m');
Cachly::deleteRegex('/\d/m');
Cachly::getItems();
[];
Cachly::each(function ($value, $key) use (&$check)
{
	$check[$key] = $value;
});
//}
echo Prof("cachly")->dumpTimers();
