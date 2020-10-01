<?php

namespace Infira\Cachly\driver;

use Infira\Utils\RuntimeMemory as Rm;
use Infira\Cachly\Cachly;

class RuntimeMemory extends \Infira\Cachly\DriverHelper
{
	public function __construct()
	{
		$this->setDriver(Cachly::RUNTIME_MEMORY);
		parent::__construct();
	}
	
	/**
	 * @inheritDoc
	 */
	public function isConfigured(): bool
	{
		return true;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doSet(string $CID, $data, int $expires = 0): bool
	{
		Rm::set($CID, $data);
		
		return true;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doExists(string $CID): bool
	{
		return Rm::exists($CID);
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGet(string $CID)
	{
		return Rm::get($CID);
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doDelete(string $CID): bool
	{
		Rm::delete($CID);
		
		return true;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGetItems(): array
	{
		return Rm::getItems();
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doFlush(): bool
	{
		return Rm::flush();
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
				self::doDelete($CID);
			}
		}
		
		return true;
	}
}

?>