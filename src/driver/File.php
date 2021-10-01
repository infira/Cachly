<?php

namespace Infira\Cachly\driver;

use Infira\Utils\File as Fm;
use Infira\Utils\Dir;
use Infira\Cachly\Cachly;
use Infira\Cachly\options\FileDriverOptions;

class File extends \Infira\Cachly\DriverHelper
{
	private $path;
	
	/**
	 * @var FileDriverOptions
	 */
	private $Options;
	
	public function __construct()
	{
		$this->setDriver(Cachly::FILE);
		if (!self::isConfigured())
		{
			Cachly::error("File driver can't be used because its not configured. Use Cachly::configureFile");
		}
		$this->Options            = Cachly::getOpt('fileOptions');
		$this->fallbackDriverName = $this->Options->fallbackDriver;
		$this->path               = $this->Options->cachePath;
		
		if (!is_dir($this->path))
		{
			$this->fallbackORShowError("'" . $this->path . "' is not a valid path");
		}
		elseif (!is_writable($this->path))
		{
			$this->fallbackORShowError("'" . $this->path . "' is not a writable");
		}
		parent::__construct();
	}
	
	/**
	 * @inheritDoc
	 */
	public static function isConfigured(): bool
	{
		return Cachly::getOpt('fileOptions') !== null;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doSet(string $CID, $data, int $expires = 0): bool
	{
		$fn = $this->getFileName($CID);
		Fm::delete($fn);
		Fm::put($fn, serialize($data));
		
		return true;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doExists(string $CID): bool
	{
		return file_exists($this->getFileName($CID));
		
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGet(string $CID)
	{
		return unserialize(Fm::getContent($this->getFileName($CID)));
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doDelete(string $CID): bool
	{
		return Fm::delete($this->getFileName($CID));
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doGetItems(): array
	{
		$output = [];
		foreach (Dir::getFileNames($this->path, ['cache']) as $f)
		{
			$CID          = str_replace('.cache', '', $f);
			$output[$CID] = $this->get($CID);
		}
		
		return $output;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function doFlush(): bool
	{
		Dir::flush($this->path);
		
		return true;
	}
	
	################ private methods
	private function getFileName(string $CID): string
	{
		return Dir::fixPath($this->path) . "$CID.cache";
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