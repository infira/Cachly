<?php

namespace Infira\Cachly\driver;

use Infira\Cachly\Cachly;

class Db extends \Infira\Cachly\DriverHelper
{
	private $mysqli;
	
	private $tableName;
	
	public function __construct()
	{
		if (!Cachly::getOpt('dbConfigured'))
		{
			Cachly::error("Db driver can't be used because its not configured. Use Cachly::configureDb");
		}
		$this->fallbackDriverName = Cachly::getOpt('dbFallbackDriver');
		if (Cachly::getOpt('dbClient'))
		{
			$this->mysqli = Cachly::getOpt('dbClient');
			if (!is_object($this->mysqli))
			{
				Cachly::error("client must be object");
			}
			if (!$this->mysqli instanceof \mysqli)
			{
				Cachly::error("client must be mysqli class");
			}
		}
		elseif (class_exists("mysqli"))
		{
			$dbName = Cachly::getOpt('dbDatabase');
			if (Cachly::getOpt("dbPort") > 0)
			{
				@$this->mysqli = new \mysqli(Cachly::getOpt('dbHost'), Cachly::getOpt('dbUser'), Cachly::getOpt('dbPass'), $dbName, Cachly::getOpt("dbPort"));
			}
			else
			{
				@$this->mysqli = new \mysqli(Cachly::getOpt('dbHost'), Cachly::getOpt('dbUser'), Cachly::getOpt('dbPass'), $dbName);
			}
			if ($this->mysqli->connect_errno)
			{
				$this->fallbackORShowError('Could not connect to database (<strong>' . $dbName . '</strong>) (' . $this->mysqli->connect_errno . ')' . $this->mysqli->connect_error);
			}
			else
			{
				$this->tableName = '`' . $this->mysqli->escape_string($dbName) . '`.`' . $this->mysqli->escape_string(Cachly::getOpt('dbTable')) . '`';
				if (is_callable(Cachly::getOpt('dbAfterConnect')))
				{
					$f = Cachly::getOpt('dbAfterConnect');
					$f->call($this->mysqli);
				}
			}
		}
		else
		{
			$this->fallbackORShowError('mysqli class does not exists, make sure that mysql is installed');
		}
		parent::__construct(Cachly::DB);
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
	protected function doSet(string $CID, $data, int $expires = 0): bool
	{
		return $this->execute('REPLACE into %tableName% (ID,data,expires) VALUES(%ID%,%data%,%expires%)', ['ID' => $CID, 'data' => $data, 'expires' => date('Y-m-d H:i:s', $expires)]) ? true : false;
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
		return $this->execute('DELETE FROM %tableName% WHERE expires < time()');
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
			$val   = $this->mysqli->real_escape_string($val);
			$query = str_replace("%$key%", "'$val'", $query);
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