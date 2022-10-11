<?php

namespace Infira\Cachly\driver;

use Exception;
use Infira\Cachly\Cachly;
use Infira\Cachly\CachlyException;
use Infira\Cachly\DriverHelper;
use Infira\Cachly\options\RedisDriverOptions;
use Redis as PHPREdis;
use RedisException;

class Redis extends DriverHelper
{
    private PHPREdis $Redis;

    /**
     * @throws CachlyException
     */
    public function __construct()
    {
        $this->setDriver(Cachly::REDIS);
        if (!self::isConfigured()) {
            Cachly::error("Redis driver can't be used because its not configured. Use Cachly::configRedis");
        }
        /**
         * @var RedisDriverOptions $opt
         */
        $opt = Cachly::getOpt('redisOptions');
        $this->fallbackDriverName = $opt->fallbackDriver;

        if ($opt->client === null) {
            try {
                if (!class_exists("Redis")) {
                    $this->fallbackORShowError('Redis class does not exists, make sure that redis is installed');
                }
                $this->Redis = new PHPREdis();
                $connect = $this->Redis->pconnect($opt->host, $opt->port);
                if (!$connect) {
                    $this->fallbackORShowError("Redis connection failed");
                }
                else {
                    $this->Redis->setOption(PHPREdis::OPT_SERIALIZER, PHPREdis::SERIALIZER_PHP);
                    if (!$this->Redis->auth($opt->password)) {
                        $this->fallbackORShowError("Redis connection authentication failed");
                    }
                    elseif (is_callable($opt->afterConnect)) {
                        $f = $opt->afterConnect;
                        $f($this->Redis);
                    }
                }
            }
            catch (Exception $exception) {
                $this->fallbackORShowError($exception->getMessage());
            }
        }
        else {
            if (!($opt->client instanceof PHPREdis)) {
                $this->fallbackORShowError("client must be Redis class");
            }
            $this->Redis = $opt->client;
        }
        parent::__construct();
    }

    /**
     * Get client
     *
     * @return PHPREdis
     */
    public function getClient(): PHPREdis
    {
        return $this->Redis;
    }

    /**
     * @inheritDoc
     */
    public static function isConfigured(): bool
    {
        return Cachly::getOpt('redisOptions') !== null;
    }

    /**
     * @inheritDoc
     * @throws RedisException
     */
    protected function doSet(string $CID, $data, int $expires = 0): bool
    {
        $set = $this->Redis->set($CID, $data);
        if ($expires > 0) {
            $this->Redis->expireAt($CID, $expires);
        }

        return $set;
    }

    /**
     * @inheritDoc
     * @throws RedisException
     */
    protected function doExists(string $CID): bool
    {
        return $this->Redis->exists($CID);
    }

    /**
     * @inheritDoc
     * @throws RedisException
     */
    protected function doGet(string $CID): mixed
    {
        return $this->Redis->get($CID);
    }

    /**
     * @inheritDoc
     * @throws RedisException
     */
    protected function doDelete(string $CID): bool
    {
        $this->Redis->del($CID);

        return true;
    }

    /**
     * @inheritDoc
     * @throws RedisException
     */
    protected function doGetItems(): array
    {
        $output = [];
        foreach ($this->Redis->keys('*') as $CID) {
            $output[$CID] = $this->get($CID);
        }

        return $output;//$this->Redis->mGet($this->Redis->keys('*'));
    }

    /**
     * @inheritDoc
     * @throws RedisException
     */
    protected function doFlush(): bool
    {
        return $this->Redis->flushDB();
    }

    /**
     * @inheritDoc
     * @throws RedisException
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