<?php

namespace Infira\Cachly\driver;

use Infira\Cachly\Cachly;
use Infira\Cachly\options\DbDriverOptions;

class Db extends \Infira\Cachly\DriverHelper
{
	private $mysqli;
	
	private $tableName;
	
	/**
	 * @var DbDriverOptions
	 */
	private $Options;
	
	public function __construct()
	{
		$this->setDriver(Cachly::DB);
		if (!self::isConfigured())
		{
			Cachly::error("Db driver can't be used because its not configured. Use Cachly::configureDb");
		}
		$this->Options            = Cachly::getOpt('dbOptions');
		$this->fallbackDriverName = $this->Options->fallbackDriver;
		
		if ($this->Options->client == null)
		{
			if (!class_exists("mysqli"))
			{
				$this->fallbackORShowError('mysqli class does not exists, make sure that mysql is installed');
			}
			$dbName = $this->Options->db;
			if ($this->Options->port !== null)
			{
				$this->mysqli = new \mysqli($this->Options->host, $this->Options->user, $this->Options->password, $dbName, $this->Options->port);
			}
			else
			{
				$this->mysqli = new \mysqli($this->Options->host, $this->Options->user, $this->Options->password, $dbName);
			}
			if ($this->mysqli->connect_errno)
			{
				$this->fallbackORShowError('Could not connect to database (<strong>' . $dbName . '</strong>) (' . $this->mysqli->connect_errno . ')' . $this->mysqli->connect_error);
			}
			else
			{
				if (is_callable($this->Options->afterConnect))
				{
					callback($this->Options->afterConnect, null, [$this->mysqli]);
				}
			}
		}
		else
		{
			$this->mysqli = $this->Options->client;
			if (!is_object($this->mysqli))
			{
				$this->fallbackORShowError("client must be object");
			}
			if (!$this->mysqli instanceof \mysqli)
			{
				$this->fallbackORShowError("client must be mysqli class");
			}
		}
		$this->tableName = '`' . $this->mysqli->escape_string($this->Options->table) . '`';
		parent::__construct();
	}
	
	/**
	 * Get client
	 *
	 * @return \mysqli
	 */
	public function getClient(): \mysqli
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
	 */
	protected function doSet(string $CID, $data, int $expires = 0): bool
	{
		if ($expires == 0)
		{
			$expires = null;
		}
		else
		{
			$expires = date('Y-m-d H:i:s', $expires);
		}
		
		return $this->execute('REPLACE INTO %tableName% (ID,DATA,expires) VALUES(%ID%,%data%,%expires%)', ['ID' => $CID, 'data' => $data, 'expires' => $expires]) ? true : false;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doExists(string $CID): bool
	{
		$res = $this->execute('SELECT ID FROM %tableName% WHERE ID = %ID%', ['ID' => $CID]);
		if (is_object($res))
		{
			if ($res instanceof \mysqli_result)
			{
				return $res->num_rows ? true : false;
			}
		}
		
		return false;
		
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGet(string $CID)
	{
		$res = $this->execute('SELECT data FROM %tableName% WHERE ID = %ID%', ['ID' => $CID]);
		if (is_object($res))
		{
			if ($res instanceof \mysqli_result)
			{
				return unserialize($res->fetch_object()->data);
			}
		}
		
		return false;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doDelete(string $CID): bool
	{
		return $this->execute('DELETE FROM %tableName% WHERE ID = %ID%', ['ID' => $CID]) ? true : false;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGetItems(): array
	{
		$output = [];
		$res    = $this->execute('SELECT ID,data FROM %tableName%');
		if (is_object($res))
		{
			if ($res instanceof \mysqli_result)
			{
				while ($row = $res->fetch_object())
				{
					$output[$row->ID] = unserialize($row->data);
				}
			}
		}
		
		return $output;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doFlush(): bool
	{
		return $this->execute('TRUNCATE TABLE %tableName%');
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGc(): bool
	{
		return $this->execute('DELETE FROM %tableName% WHERE expires < TIME() AND expires IS NOT NULL');
	}
	
	################ private methods
	
	
	private function execute(string $query, array $data = [])
	{
		if (array_key_exists('data', $data))
		{
			if (is_object($data['data']) or is_array($data['data']))
			{
				$data['data'] = serialize($data['data']);
			}
		}
		foreach ($data as $key => $val)
		{
			if ($key == 'expires' and $val === null)
			{
				$query = str_replace("%$key%", 'NULL', $query);
			}
			else
			{
				$val   = $this->mysqli->real_escape_string($val);
				$query = str_replace("%$key%", "'$val'", $query);
			}
		}
		$query = str_replace("%tableName%", $this->tableName, $query);
		$res   = $this->mysqli->query($query);
		if ($this->mysqli->error)
		{
			Cachly::error('mysqli error ' . $this->mysqli->error . ' for query ' . $query);
		}
		
		return $res;
	}
}

?>