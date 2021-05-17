<?php

use Infira\Cachly\Cachly;

require "initTest.php";

Cachly::$Driver->Db->gc();
Cachly::$Driver->File->gc();
Cachly::$Driver->Mem->gc();
Cachly::$Driver->Redis->gc();
Cachly::$Driver->Rm->gc();
Cachly::$Driver->Sess->gc();