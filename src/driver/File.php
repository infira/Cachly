<?php

namespace Infira\Cachly\driver;

use Exception;
use Infira\Cachly\Cachly;
use Infira\Cachly\CachlyException;
use Infira\Cachly\DriverHelper;
use Infira\Cachly\options\FileDriverOptions;
use Wolo\File\Folder;
use Wolo\File\Path;

class File extends DriverHelper
{
    private string $path;

    /**
     * @throws CachlyException
     */
    public function __construct()
    {
        $this->setDriver(Cachly::FILE);
        if (!self::isConfigured()) {
            Cachly::error("File driver can't be used because its not configured. Use Cachly::configureFile");
        }
        /**
         * @var FileDriverOptions $opt
         */
        $opt = Cachly::getOpt('fileOptions');
        $this->fallbackDriverName = $opt->fallbackDriver;
        $this->path = $opt->cachePath;

        if (!is_dir($this->path)) {
            $this->fallbackORShowError("'" . $this->path . "' is not a valid path");
        }
        elseif (!is_writable($this->path)) {
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
        \Wolo\File\File::delete($fn);
        \Wolo\File\File::put($fn, serialize($data));

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
    protected function doGet(string $CID): mixed
    {
        return unserialize(\Wolo\File\File::content($this->getFileName($CID)));
    }

    /**
     * @inheritDoc
     */
    protected function doDelete(string $CID): bool
    {
        \Wolo\File\File::delete($this->getFileName($CID));

        return true;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function doGetItems(): array
    {
        $output = [];
        foreach (Folder::fileNames($this->path, 'cache') as $f) {
            $CID = str_replace('.cache', '', $f);
            $output[$CID] = $this->get($CID);
        }

        return $output;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function doFlush(): bool
    {
        Folder::flush($this->path);

        return true;
    }

    ################ private methods
    private function getFileName(string $CID): string
    {
        return Path::join($this->path, "$CID.cache");
    }

    /**
     * @inheritDoc
     * @throws Exception
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