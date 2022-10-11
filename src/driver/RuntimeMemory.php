<?php

namespace Infira\Cachly\driver;

use Infira\Cachly\Cachly;
use Infira\Cachly\DriverHelper;
use Wolo\Globals\Globals;

class RuntimeMemory extends DriverHelper
{
    public function __construct()
    {
        $this->setDriver(Cachly::RUNTIME_MEMORY);
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function doSet(string $CID, $data, int $expires = 0): bool
    {
        Globals::set($CID, $data);

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function doExists(string $CID): bool
    {
        return Globals::exists($CID);
    }

    /**
     * @inheritDoc
     */
    protected function doGet(string $CID): mixed
    {
        return Globals::get($CID);
    }

    /**
     * @inheritDoc
     */
    protected function doDelete(string $CID): bool
    {
        Globals::delete($CID);

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function doGetItems(): array
    {
        return Globals::all();
    }

    /**
     * @inheritDoc
     */
    protected function doFlush(): bool
    {
        return Globals::flush();
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