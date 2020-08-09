<?php

namespace Infira\Cachly\driver;

use Infira\Cachly\Cachly;

class Session extends \Infira\Cachly\DriverHelper
{
	public function __construct()
	{
		if (!isset($_SESSION))
		{
			Cachly::error("Session driver can't be used because session is not started. Use session_start()");
		}
		parent::__construct(Cachly::SESS);
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doSet(string $CID, $data, int $expires = 0): bool
	{
		$_SESSION[$CID]                      = $data;
		$_SESSION['cachlySessionCIDS'][$CID] = 1;
		
		return true;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doExists(string $CID): bool
	{
		return array_key_exists($CID, $_SESSION);
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGet(string $CID)
	{
		return $_SESSION[$CID];
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doDelete(string $CID): bool
	{
		unset($_SESSION[$CID]);
		unset($_SESSION['cachlySessionCIDS'][$CID]);
		
		return true;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGetItems(): array
	{
		$output = [];
		foreach ($_SESSION['cachlySessionCIDS'] as $CID => $v)
		{
			$CID          = substr($CID, 1); //removes letter c in front of it
			$output[$CID] = $_SESSION[$CID];
		}
		
		return $output;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doFlush(): bool
	{
		if (!isset($_SESSION['cachlySessionCIDS']))
		{
			return false;
		}
		foreach ($_SESSION['cachlySessionCIDS'] as $CID => $v)
		{
			unset($_SESSION['cachlySessionCIDS'][$CID]);
			unset($_SESSION[$CID]);
		}
		
		return true;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGc(): bool
	{
		$now = time();
		foreach ($this->doGetItems() as $CID => $v)
		{
			if (is_object($v) and isset($v->t) and $now > $v->t)
			{
				unset($_SESSION[$CID]);
				unset($_SESSION['cachlySessionCIDS'][$CID]);
			}
		}
		
		return true;
	}
}

?>