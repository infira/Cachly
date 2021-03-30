<?php

use Infira\Cachly\Cachly;
use Infira\Utils\Http as Http;
use Infira\Utils\Date;
use Infira\Utils\Gen;

require "initTest.php";

Cachly::$Driver->Db->flush();
Cachly::$Driver->File->flush();
Cachly::$Driver->Mem->flush();
Cachly::$Driver->Redis->flush();
Cachly::$Driver->Rm->flush();
Cachly::$Driver->Sess->flush();

foreach ($drivers as $driver)
{
	Cachly::setDefaultDriver($driver);
	print("===============================================> Driver <strong>" . $driver . '</strong>: setting values<br >');
	
	Sess::set("$driver-controll-time", strtotime('+5 seconds'));
	Sess::set("$driver-deleteRegex-activated", false);
	
	Cachly::set("cacheKey", "cacheValue");
	Cachly::set("regularExpressionKey", "regular expression value");
	Cachly::Collection("collection")->set("collection cacheKey1", "collection cacheKey1 value");
	Cachly::Collection("collection")->set("collection cacheKey2", "collection cacheKey2 value");
	Cachly::Collection("collection")->Collection("subCollection")->set("sub collection cacheKey", "subCollection cache value");
	Cachly::set('expireable5Sec', 'expire value', '+5 seconds');
	
	Cachly::once("once cache key", function ()
	{
		return "once cache value";
	});
	
	Cachly::onceExpire("expireable5Sec once", function ()
	{
		return "expired once cache value";
	}, "+5 seconds");
	
	/*
	$DriverInstance = Cachly::$driver("testingInstance");
	$DriverInstance->set("cacheKey", "cacheValue");
	$DriverInstance->set("regularExpressionKey", "regular expression value");
	$DriverInstance->Collection("collection")->set("collection cacheKey1", "collection cache value");
	$DriverInstance->Collection("collection")->set("collection cacheKey2", "collection cache value");
	$DriverInstance->Collection("collection")->Collection("subCollection")->set("sub collection cacheKey", "collection cache value");
	$DriverInstance->set('expireable5Sec', 'expire value', '+5 seconds');
	*/
	
	
	/**
	 * @var \Infira\Cachly\Cacher $Collection
	 * $tree = [];
	 * Cachly::eachCollection(function ($Collection, $name) use (&$tree)
	 * {
	 * $tree[$name] = ["items" => $Collection->getItems(), 'collections' => []];
	 * $parentName  = $name;
	 * $Collection->eachCollection(function ($Collection, $name) use (&$tree, $parentName)
	 * {
	 * $tree[$parentName]['collections'][$name]['items'] = $Collection->getItems();
	 * });
	 * });
	 */
	
	if (Http::existsGET('single'))
	{
		$items = Cachly::getDriver()->getItems();
		ksort($items);
		debug(['Driver->getItems' => $items]);
		debug(['getIDKeyPairs' => Cachly::getIDKeyPairs()]);
		debug(['getItems' => Cachly::getItems()]);
	}
	/*
	debug(['aaaa'=>aaaaaaaaa]);
	debug(['aaaa'=>aaaaaaaaa]);
	debug(['aaaa'=>aaaaaaaaa]);
	debug(['aaaa'=>aaaaaaaaa]);
	debug(['aaaa'=>aaaaaaaaa]);
	debug(['aaaa'=>aaaaaaaaa]);
	debug(['aaaa'=>aaaaaaaaa]);
	debug(['aaaa'=>aaaaaaaaa]);
	debug(['aaaa'=>aaaaaaaaa]);
	debug(['aaaa'=>aaaaaaaaa]);
	debug(['aaaa'=>aaaaaaaaa]);
	debug(['aaaa'=>aaaaaaaaa]);
	debug(['aaaa'=>aaaaaaaaa]);
	debug(['aaaa'=>aaaaaaaaa]);
	debug(['aaaa'=>aaaaaaaaa]);
	*/
	
	
	print("===============================================> Driver <strong>" . $driver . '</strong>: values saved<br >');
}