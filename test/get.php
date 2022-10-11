<?php

require '../vendor/autoload.php';

use Infira\Cachly\Cachly;
use Wolo\Request\Session;

$Handler = new \Infira\Error\Handler();
try {
    require "initTest.php";
    foreach ($drivers as $driver) {
        if ($driver == Cachly::RUNTIME_MEMORY) {
            continue;
        }
        $errorCount = 0;
        $throw = function ($isInstance, $msg, $dump = '__DEF__') use (&$errorCount) {
            if ($isInstance) {
                $msg = "INSTANCE: " . $msg;
            }
            echo '<span style="color:red">' . $msg . '</span>' . '<br />';
            if ($dump !== '__DEF__') {
                debug(['$dump' => $dump]);
            }
            $errorCount++;
        };
        Cachly::setDefaultDriver($driver);

        print("===============================================> Driver <strong>$driver</strong>: check stored data<br >");
        $deleteRegexAcitvated = Session::get("$driver-deleteRegex-activated");
        $now = time();
        $controlTime = Session::get("$driver-controll-time");
        $checkValues = [
            'collection cacheKey1' => [Cachly::Collection("collection")->get('collection cacheKey1'), 'collection cacheKey1 value'],
            'collection cacheKey2' => [Cachly::Collection("collection")->get('collection cacheKey2'), 'collection cacheKey2 value'],
            'subCollection' => [Cachly::Collection('collection')->Collection('subCollection')->get('sub collection cacheKey'), 'subCollection cache value'],
            'cacheKey' => [Cachly::get('cacheKey'), 'cacheValue'],
            'expiresAt(cacheKey)' => [Cachly::expiresAt("cacheKey"), 'never'],
        ];
        if ($now <= $controlTime) {
            $checkValues['expireable5Sec->beforeExpire'] = [Cachly::expiresAt("expireable5Sec"), $controlTime];
        }
        else {
            $checkValues['expireable5Sec->afterExpire'] = [Cachly::expiresAt("expireable5Sec"), 'expired'];
        }
        foreach ($checkValues as $name => $row) {
            [$realValue, $correctValue] = $row;
            if ($realValue !== $correctValue) {
                $throw(false, "single value('$name') failed", [
                    'realValue' => $realValue,
                    'correctValue' => $correctValue
                ]);
            }
        }

        //debug("controlTime", $controlTime, $_SESSION);
        ######## Collections test
        $checkItems = [];
        $check = [];
        Cachly::eachCollection(static function ($Collection, $name) use (&$check) {
            $check[$name] = ["items" => $Collection->getItems(), 'collections' => []];
            $parentName = $name;
            $Collection->eachCollection(function ($Collection, $name) use (&$check, $parentName) {
                $check[$parentName]['collections'][$name]['items'] = $Collection->getItems();
            });
        });
        $checkItems['tree()'] = ['bed59944', $check];
        $checkItems['collection->getItems()'] = ['e2a612cb', Cachly::Collection("collection")->getItems()];
        $checkItems['collection->subCollection getItems()'] = ['e86be868', Cachly::Collection("collection")->Collection("subCollection")->getItems()];

        foreach ($checkItems as $name => $_row) {
            [$correctCID, $data] = $_row;
            $dataCID = Cachly::generateCacheID($data);
            if ($correctCID !== $dataCID) {
                $throw(false, "$name failed", [$dataCID => $data, 'correctCID' => $correctCID]);
            }
        }
        Cachly::deletedExpired();
        $checkExpire = [];
        $CIDS = [];
        $CIDS['afterExpire->afterDeleteRegex'] = '0b5abc95';
        $CIDS['afterExpire->beforeDeleteRegex'] = '0b5abc95';
        $CIDS['beforeExpire->afterDeleteRegex'] = '89bd37c4';
        $CIDS['beforeExpire->beforeDeleteRegex'] = '43e7dbb1';

        $checkExpire['getItems'] = ['check' => Cachly::getItems(), 'CIDS' => $CIDS];
        foreach ($checkExpire as $name => $_row) {
            $Item = (object)$_row;
            $check = $Item->check;
            $aCID = Cachly::generateCacheID($check);
            if (time() > $controlTime) {
                if ($deleteRegexAcitvated) {
                    if ($aCID !== $Item->CIDS['afterExpire->afterDeleteRegex']) {
                        $throw(false, "$name(afterExpire->afterDeleteRegex) failed", [$aCID => $check, 'correctCID' => $Item->CIDS['afterExpire->afterDeleteRegex']]);
                    }
                }
                else {
                    if ($aCID !== $Item->CIDS['afterExpire->beforeDeleteRegex']) {
                        $throw(false, "$name(afterExpire->beforeDeleteRegex) failed", [$aCID => $check, 'correctCID' => $Item->CIDS['afterExpire->beforeDeleteRegex']]);
                    }
                }
            }
            else {
                if ($deleteRegexAcitvated) {
                    if ($aCID !== $Item->CIDS['beforeExpire->afterDeleteRegex']) {
                        $throw(false, "$name(beforeExpire->afterDeleteRegex) failed", [$aCID => $check, 'correctCID' => $Item->CIDS['beforeExpire->afterDeleteRegex']]);
                    }
                }
                else {
                    if ($aCID !== $Item->CIDS['beforeExpire->beforeDeleteRegex']) {
                        $throw(false, "$name(beforeExpire->beforeDeleteRegex) failed", [$aCID => $check, 'correctCID' => $Item->CIDS['beforeExpire->beforeDeleteRegex']]);
                    }
                }
            }
        }
        Cachly::deleteRegex('/Expression/');
        if (time() > $controlTime) {
            //########### isExpired
            $check = Cachly::isExpired('expireable5Sec');
            if ($check === false) {
                $throw(false, 'isExpired(afterExpire) failed', $check);
            }

            //########### expiresAt
            $check = Cachly::expiresAt('expireable5Sec');
            if ($check !== 'expired') {
                $throw(false, 'expiresAt(afterExpire) failed', $check);
            }
        }
        else {
            //########### isExpired
            if (Cachly::isExpired('expireable5Sec') === true) {
                $throw(false, 'isExpired(beforeExpire) failed');
            }

            //########### expiresAt
            $check = Cachly::expiresAt('expireable5Sec');
            if (!is_int($check)) {
                $throw(false, 'expiresAt(beforeExpire) failed', $check);
            }
        }

        $check = Cachly::once("once cache key", function () {
            return "once cache new value value";
        });
        if ($check !== 'once cache value') {
            $throw(false, 'once() failed', $check);
        }


        ########################################################################## test instance
        //$DriverInstance = Cachly::$driver("testingInstance");


        Session::set("$driver-deleteRegex-activated", true);

        if ($errorCount) {
            $items = Cachly::getDriver()->getItems();
            ksort($items);
            debug(['Driver->getItems' => $items]);
            debug(['getIDKeyPairs' => Cachly::getIDKeyPairs()]);
            debug(['getItems' => Cachly::getItems()]);
            print("===============================================> Driver <strong>$driver" . '</strong> test finished:<strong style="color:red"> FAILED</strong><br ><br ><br >');
        }
        else {
            print("===============================================> Driver <strong>$driver" . '</strong> test finished:<strong style="color:green"> SUCCESS</strong><br ><br ><br >');
        }
    }
}
catch (\Infira\Error\Error $e) {
    echo $e->getHTMLTable();
}
catch (Throwable $e) {
    echo $Handler->catch($e)->getHTMLTable();
}