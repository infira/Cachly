<?php

namespace Infira\Cachly\driver;

use Infira\Cachly\Cachly;
use Infira\Cachly\CachlyException;
use Infira\Cachly\DriverHelper;
use Infira\Cachly\options\DbDriverOptions;
use mysqli as PHPMysqlI;
use mysqli_result;
use Wolo\Date\Date;

class Db extends DriverHelper
{
    private PHPMysqlI $mysqli;

    private string $tableName;

    /**
     * @throws CachlyException
     */
    public function __construct()
    {
        $this->setDriver(Cachly::DB);
        if (!self::isConfigured()) {
            Cachly::error("Db driver can't be used because its not configured. Use Cachly::configureDb");
        }
        /**
         * @var DbDriverOptions $opt
         */
        $opt = Cachly::getOpt('dbOptions');
        $this->fallbackDriverName = $opt->fallbackDriver;

        if ($opt->client === null) {
            if (!class_exists("mysqli")) {
                $this->fallbackORShowError('mysqli class does not exists, make sure that mysql is installed');
            }
            $dbName = $opt->db;
            if ($opt->port !== null) {
                $this->mysqli = new PHPMysqlI($opt->host, $opt->user, $opt->password, $dbName, $opt->port);
            }
            else {
                $this->mysqli = new PHPMysqlI($opt->host, $opt->user, $opt->password, $dbName);
            }
            if ($this->mysqli->connect_errno) {
                $this->fallbackORShowError('Could not connect to database (<strong>' . $dbName . '</strong>) (' . $this->mysqli->connect_errno . ')' . $this->mysqli->connect_error);
            }
            elseif (is_callable($opt->afterConnect)) {
                call_user_func($opt->afterConnect, $this->mysqli);
            }
        }
        else {
            if (!$opt->client instanceof PHPMysqlI) {
                $this->fallbackORShowError("client must be mysqli class");
            }
            $this->mysqli = $opt->client;
        }
        $this->tableName = '`' . $this->mysqli->escape_string($opt->table) . '`';
        parent::__construct();
    }

    /**
     * Get client
     *
     * @return PHPMysqlI
     */
    public function getClient(): PHPMysqlI
    {
        return $this->mysqli;
    }

    /**
     * @inheritDoc
     */
    public static function isConfigured(): bool
    {
        return Cachly::getOpt('dbOptions') !== null;
    }

    /**
     * @inheritDoc
     * @throws CachlyException
     */
    protected function doSet(string $CID, $data, int $expires = 0): bool
    {
        $expiresIn = $expires === 0 ? null : Date::of($expires)->sqlDateTime();

        return (bool)$this->execute(
            'REPLACE INTO %tableName (ID,data,expires) VALUES(%ID,%data,%expires)',
            [
                'ID' => $CID,
                'data' => $data,
                'expires' => $expiresIn
            ]);
    }

    /**
     * @inheritDoc
     * @throws CachlyException
     */
    protected function doExists(string $CID): bool
    {
        $res = $this->execute('SELECT ID FROM %tableName WHERE ID = %ID', ['ID' => $CID]);
        if (!$res) {
            return false;
        }
        if ($res instanceof mysqli_result) {
            return (bool)$res->num_rows;
        }

        return false;
    }

    /**
     * @inheritDoc
     * @throws CachlyException
     */
    protected function doGet(string $CID): mixed
    {
        $res = $this->execute('SELECT data FROM %tableName WHERE ID = %ID', ['ID' => $CID]);
        if (!$res) {
            return null;
        }
        if ($res instanceof mysqli_result) {
            return unserialize($res->fetch_object()->data);
        }

        return null;
    }

    /**
     * @inheritDoc
     * @throws CachlyException
     */
    protected function doDelete(string $CID): bool
    {
        return (bool)$this->execute('DELETE FROM %tableName WHERE ID = %ID', ['ID' => $CID]);
    }

    /**
     * @inheritDoc
     * @throws CachlyException
     */
    protected function doGetItems(): array
    {
        $output = [];
        $res = $this->execute('SELECT ID,data FROM %tableName');
        if (!$res) {
            return [];
        }
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_object()) {
                $output[$row->ID] = unserialize($row->data);
            }
        }

        return $output;
    }

    /**
     * @inheritDoc
     * @throws CachlyException
     */
    protected function doFlush(): bool
    {
        return (bool)$this->execute('TRUNCATE TABLE %tableName');
    }

    /**
     * @inheritDoc
     * @throws CachlyException
     */
    protected function doGc(): bool
    {
        return (bool)$this->execute('DELETE FROM %tableName WHERE expires < CURRENT_TIMESTAMP () AND expires IS NOT NULL');
    }

    ################ private methods


    /**
     * @throws CachlyException
     */
    private function execute(string $query, array $data = []): mysqli_result|bool
    {
        if (array_key_exists('data', $data)) {
            if (is_object($data['data']) || is_array($data['data'])) {
                $data['data'] = serialize($data['data']);
            }
        }
        foreach ($data as $key => $val) {
            $replaceKey = '%' . $key;
            if ($key === 'expires' && $val === null) {
                $query = str_replace($replaceKey, 'NULL', $query);
            }
            else {
                $val = $this->mysqli->real_escape_string($val);
                $query = str_replace($replaceKey, "'$val'", $query);
            }
        }
        $query = str_replace("%tableName", $this->tableName, $query);
        $res = $this->mysqli->query($query);
        if ($this->mysqli->error) {
            Cachly::error('mysqli error ' . $this->mysqli->error . ' for query ' . $query);
        }

        return $res;
    }
}