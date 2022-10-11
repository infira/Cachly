<?php

require '../vendor/autoload.php';

use Infira\Cachly\Cachly;
use Wolo\Request\Http;
use Wolo\Request\Session;

$Handler = new \Infira\Error\Handler();
try {
    require 'initTest.php';

    Cachly::$Driver->Db->flush();
    Cachly::$Driver->File->flush();
//Cachly::$Driver->Mem->flush();
//Cachly::$Driver->Redis->flush();
    Cachly::$Driver->Rm->flush();
    Cachly::$Driver->Sess->flush();

    foreach ($drivers as $driver) {
        Cachly::setDefaultDriver($driver);
        print('===============================================> Driver <strong>' . $driver . '</strong>: setting values<br >');

        Session::set("$driver-controll-time", strtotime('+5 seconds'));
        Session::set("$driver-deleteRegex-activated", false);

        Cachly::set('cacheKey', 'cacheValue');
        Cachly::Collection('collection')->set('collection cacheKey1', 'collection cacheKey1 value');
        Cachly::Collection('collection')->set('collection cacheKey2', 'collection cacheKey2 value');
        Cachly::Collection('collection')->Collection('subCollection')->set('sub collection cacheKey', 'subCollection cache value');

        Cachly::set('regularExpressionKey', 'regular expression value');
        Cachly::set('expireable5Sec', 'expire value', '+5 seconds');

        Cachly::once('once cache key', static function () {
            return 'once cache value';
        });

        Cachly::onceExpire('expireable5Sec once', static function () {
            return 'expired once cache value';
        }, '+5 seconds');

        if (Http::existsGET('single')) {
            $items = Cachly::getDriver()->getItems();
            ksort($items);
            debug(['Driver->getItems' => $items]);
            debug(['getIDKeyPairs' => Cachly::getIDKeyPairs()]);
            debug(['getItems' => Cachly::getItems()]);
        }


        print('===============================================> Driver <strong>' . $driver . '</strong>: values saved<br >');
    }
}
catch (\Infira\Error\Error $e) {
    echo $e->getHTMLTable();
}
catch (Throwable $e) {
    echo $Handler->catch($e)->getHTMLTable();
}
