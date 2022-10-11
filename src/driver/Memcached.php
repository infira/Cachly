<?php

namespace Infira\Cachly\driver;

use Infira\Cachly\Cachly;
use Infira\Cachly\CachlyException;
use Infira\Cachly\DriverHelper;
use Infira\Cachly\options\MemcachedDriverOptions;
use Memcached as PHPMemcached;
use Wolo\Date\Date;

class Memcached extends DriverHelper
{
    public PHPMemcached $Memcached;

    /**
     * @throws CachlyException
     */
    public function __construct()
    {
        $this->setDriver(Cachly::MEM);
        if (!self::isConfigured()) {
            Cachly::error("Memcached driver can't be used because its not configured. Use Cachly::configMemcached");
        }

        /**
         * @var MemcachedDriverOptions $opt
         */
        $opt = Cachly::getOpt('redisOptions');
        $this->fallbackDriverName = $opt->fallbackDriver;

        if ($opt->client === null) {
            if (!class_exists('Memcached')) {
                $this->fallbackORShowError('Memcached class does not exists, make sure that memcached is installed');
            }
            $this->Memcached = new PHPMemcached();
            $connect = $this->Memcached->addServer($opt->host, (int)$opt->port);
            $ok = true;
            if ($this->Memcached->getStats() === false) {
                $this->fallbackORShowError('Memcached connection failed');
                $ok = false;
            }
            if (!$connect) {
                $this->fallbackORShowError('Memcached connection failed');
                $ok = false;
            }
            if (is_callable($opt->afterConnect) && $ok) {
                call_user_func($opt->afterConnect, $this->Memcached);
            }
        }
        else {
            if (!$opt->client instanceof PHPMemcached) {
                Cachly::error("client must be Memcached class");
            }
            $this->Memcached = $opt->client;
        }
        parent::__construct();
    }

    /**
     * Get client
     *
     * @return PHPMemcached
     */
    public function getClient(): PHPMemcached
    {
        return $this->Memcached;
    }

    /**
     * @inheritDoc
     */
    public static function isConfigured(): bool
    {
        return Cachly::getOpt('memcachedOptions') !== null;
    }

    /**
     * @inheritDoc
     */
    protected function doSet(string $CID, $data, int $expires = 0): bool
    {
        $expiresIn = $expires;
        if (is_string($expires)) {
            $expiresIn = Date::of($expires)->time();
        }

        return $this->Memcached->set($CID, $data, $expiresIn);
    }

    /**
     * @inheritDoc
     */
    protected function doExists(string $CID): bool
    {
        return $this->Memcached->get($CID) !== false || $this->Memcached->getResultCode() !== PHPMemcached::RES_NOTFOUND;
    }

    /**
     * @inheritDoc
     */
    protected function doGet(string $CID): mixed
    {
        return $this->Memcached->get($CID);
    }

    /**
     * @inheritDoc
     */
    protected function doDelete(string $CID): bool
    {
        return $this->Memcached->delete($CID);
    }

    /**
     * @inheritDoc
     */
    protected function doGetItems(): array
    {
        $keys = $this->Memcached->getAllKeys();
        if ($keys === false) {
            return [];
        }

        return $this->Memcached->getMulti($keys);
    }

    /**
     * @inheritDoc
     */
    protected function doFlush(): bool
    {
        return $this->Memcached->flush();
    }

    public function getStats()
    {
        $stats = $this->Memcached->getStats();
        $stats = $stats["localhost:11211"];
        $stats["megaBytes"] = $stats["bytes"] / 1048576;
        $stats["limitMaxMegaBYtes"] = $stats["limit_maxbytes"] / 1048576;

        return $stats;
    }

    /**
     * @inheritDoc
     */
    protected function doGc(): bool
    {
        $now = time();
        foreach ($this->doGetItems() as $CID => $v) {
            if (is_object($v) && isset($v->t) && $now > $v->t) {
                $this->doDelete($CID);
            }
        }

        return true;
    }
}