<?php

use Infira\Cachly\Cachly;
use Infira\Utils\Gen as Gen;
use Infira\Utils\Http as Http;
use Infira\Cachly\Cacher;
use Infira\Utils\Date;

require "initTest.php";
Cachly::init();
foreach ($drivers as $driver)
{
	if ($driver == Cachly::RUNTIME_MEMORY)
	{
		continue;
	}
	$errorCount = 0;
	$throw      = function ($isInstance, $msg, $dump = '__DEF__') use (&$errorCount)
	{
		if ($isInstance)
		{
			$msg = "INSTANCE: " . $msg;
		}
		echo '<font color="red">' . $msg . '</font>' . '<br />';
		if ($dump !== '__DEF__')
		{
			debug($dump);
		}
		$errorCount++;
	};
	Cachly::setDefaultDriver($driver);
	
	print("===============================================> Driver <strong>$driver</strong>: check stored data<br >");
	$deleteRegexAcitvated = Sess::get("$driver-deleteRegex-activated");
	$controlTime          = Sess::get("$driver-controll-time");
	//debug("controlTime", $controlTime, $_SESSION);
	######## Collections test
	$checkItems = [];
	$check      = [];
	Cachly::eachCollection(function ($Collection, $name) use (&$check)
	{
		$check[$name] = ["items" => $Collection->getItems(), 'collections' => []];
		$parentName   = $name;
		$Collection->eachCollection(function ($Collection, $name) use (&$check, $parentName)
		{
			$check[$parentName]['collections'][$name]['items'] = $Collection->getItems();
		});
	});
	$checkItems['tree()']                               = ['e98c4712', $check, true];
	$checkItems['collection->getItems()']               = ['c2214a3e', Cachly::Collection("collection")->getItems(), true];
	$checkItems['collection->get()']                    = ['collection cacheKey2 value', Cachly::Collection("collection")->get("collection cacheKey2"), false];
	$checkItems['collection->get()']                    = ['subCollection cache value', Cachly::Collection("collection")->Collection("subCollection")->get("sub collection cacheKey"), false];
	$checkItems['collection->subCollection getItems()'] = ['457fb6af', Cachly::Collection("collection")->Collection("subCollection")->getItems(), true];
	$checkItems['get()']                                = ['cacheValue', Cachly::get("cacheKey"), false];
	$checkItems['expiresAt(cacheKey)']                  = ['never', Cachly::expiresAt("cacheKey"), false];
	foreach ($checkItems as $name => $_row)
	{
		[$checkCID, $check, $checkAsCID] = $_row;
		$aCID = $check;
		if ($checkAsCID)
		{
			$aCID = Gen::cacheID($check);
		}
		if ($aCID != $checkCID)
		{
			$throw(false, "$name failed", [$aCID => $check]);
		}
	}
	Cachly::deletedExpired();
	$checkItems                              = [];
	$CIDS                                    = [];
	$CIDS['afterExpire->afterDeleteRegex']   = '02f7bcc3';
	$CIDS['afterExpire->beforeDeleteRegex']  = 'f4c53857';
	$CIDS['beforeExpire->afterDeleteRegex']  = '37ea9a75';
	$CIDS['beforeExpire->beforeDeleteRegex'] = '5f14b49d';
	
	$checkItems['getItems'] = ['check' => Cachly::getItems(), 'CIDS' => $CIDS];
	foreach ($checkItems as $name => $_row)
	{
		$Item  = (object)$_row;
		$check = $Item->check;;
		$aCID = Gen::cacheID($check);
		if (time() > $controlTime)
		{
			if ($deleteRegexAcitvated)
			{
				if ($aCID != $Item->CIDS['afterExpire->afterDeleteRegex'])
				{
					$throw(false, "$name(afterExpire->afterDeleteRegex) failed", [$aCID => $check]);
				}
			}
			else
			{
				if ($aCID != $Item->CIDS['afterExpire->beforeDeleteRegex'])
				{
					$throw(false, "$name(afterExpire->beforeDeleteRegex) failed", [$aCID => $check]);
				}
			}
		}
		else
		{
			if ($deleteRegexAcitvated)
			{
				
				if ($aCID != $Item->CIDS['beforeExpire->afterDeleteRegex'])
				{
					$throw(false, "$name(beforeExpire->afterDeleteRegex) failed", [$aCID => $check]);
				}
			}
			else
			{
				if ($aCID != $Item->CIDS['beforeExpire->beforeDeleteRegex'])
				{
					$throw(false, "$name(beforeExpire->beforeDeleteRegex) failed", [$aCID => $check]);
				}
			}
		}
	}
	Cachly::deleteRegex('Expression');
	if (time() > $controlTime)
	{
		//########### isExpired
		$check = Cachly::isExpired('expireable5Sec');
		if ($check === false)
		{
			$throw(false, 'isExpired(afterExpire) failed', $check);
		}
		
		//########### expiresAt
		$check = Cachly::expiresAt('expireable5Sec');
		if ($check !== null)
		{
			$throw(false, 'expiresAt(afterExpire) failed', $check);
		}
	}
	else
	{
		//########### isExpired
		if (Cachly::isExpired('expireable5Sec') === true)
		{
			$throw(false, 'isExpired(beforeExpire) failed');
		}
		
		//########### expiresAt
		$check = Cachly::expiresAt('expireable5Sec');
		if (!is_int($check))
		{
			$throw(false, 'expiresAt(beforeExpire) failed', $check);
		}
	}
	
	$check = Cachly::once("once cache key", function ()
	{
		return "once cache new value value";
	});
	if ($check != 'once cache value')
	{
		$throw(false, 'once() failed', $check);
	}
	
	
	########################################################################## test instance
	//$DriverInstance = Cachly::$driver("testingInstance");
	
	
	Sess::set("$driver-deleteRegex-activated", true);
	
	if ($errorCount)
	{
		$items = Cachly::getDriver()->getItems();
		ksort($items);
		debug(['Driver->getItems' => $items]);
		debug(['getIDKeyPairs' => Cachly::getIDKeyPairs()]);
		debug(['getItems' => Cachly::getItems()]);
		print("===============================================> Driver <strong>$driver" . '</strong> test finished:<strong style="color:red"> FAILED</strong><br ><br ><br >');
	}
	else
	{
		print("===============================================> Driver <strong>$driver" . '</strong> test finished:<strong style="color:green"> SUCCESS</strong><br ><br ><br >');
	}
}