<?php

namespace Infira\Cachly\driver;

use Infira\Cachly\Cachly;
use Infira\Cachly\CachlyException;
use Infira\Cachly\DriverHelper;

class Session extends DriverHelper
{
    /**
     * @throws CachlyException
     */
    public function __construct()
    {
        $this->setDriver(Cachly::SESS);
        if (!self::isConfigured()) {
            Cachly::error("Session driver can't be used because session is not started. Use session_start()");
        }
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    public static function isConfigured(): bool
    {
        if (!isset($_SESSION)) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function doSet(string $CID, $data, int $expires = 0): bool
    {
        $_SESSION[$CID] = $data;
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
    protected function doGet(string $CID): mixed
    {
        return $_SESSION[$CID];
    }

    /**
     * @inheritDoc
     */
    protected function doDelete(string $CID): bool
    {
        unset($_SESSION[$CID], $_SESSION['cachlySessionCIDS'][$CID]);

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function doGetItems(): array
    {
        $output = [];
        foreach ($_SESSION['cachlySessionCIDS'] as $CID => $v) {
            $output[$CID] = $_SESSION[$CID];
        }

        return $output;
    }

    /**
     * @inheritDoc
     */
    protected function doFlush(): bool
    {
        if (!isset($_SESSION['cachlySessionCIDS'])) {
            return false;
        }
        foreach ($_SESSION['cachlySessionCIDS'] as $CID => $v) {
            unset($_SESSION['cachlySessionCIDS'][$CID], $_SESSION[$CID]);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function doGc(): bool
    {
        $now = time();
        foreach ($this->doGetItems() as $CID => $v) {
            if (is_object($v) && isset($v->t) && $now > $v->t) {
                unset($_SESSION[$CID], $_SESSION['cachlySessionCIDS'][$CID]);
            }
        }

        return true;
    }
}