<?php

namespace Infira\Cachly\Adapter;

use RuntimeException;
use Symfony\Component\Cache\Adapter\AbstractAdapter;

class SessionAdapter extends AbstractAdapter
{
    protected $maxIdLength = 255;

    public function __construct(private readonly string $namespace = '')
    {
        if (!isset($_SESSION)) {
            throw new RuntimeException("Session adapter can't be used because session is not started. Use session_start()");
        }
        parent::__construct($namespace, 0);
    }


    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids): iterable
    {
        $values = [];

        foreach ($ids as $id) {
            if ($this->doHave($id)) {
                $values[$id] = $_SESSION[$this->namespace][$id];
            }
        }

        return $values;
    }

    protected function doHave(string $id): bool
    {
        if (!isset($_SESSION[$this->namespace][$id])) {
            return false;
        }
        if (isset($_SESSION["$this->namespace-expires"][$id]) && time() > $_SESSION["$this->namespace-expires"][$id]) {
            return false;
        }

        return true;
    }

    protected function doClear(string $namespace): bool
    {
        $_SESSION[$this->namespace] = [];
        unset($_SESSION["$this->namespace-expires"]);

        return true;
    }

    protected function doDelete(array $ids): bool
    {
        foreach ($ids as $id) {
            if (isset($_SESSION[$this->namespace][$id])) {
                unset($_SESSION[$this->namespace][$id]);
                if (isset($_SESSION["$this->namespace-expires"][$id])) {
                    unset($_SESSION["$this->namespace-expires"][$id]);
                }
            }
        }

        return true;
    }

    protected function doSave(array $values, int $lifetime): array|bool
    {
        foreach ($values as $id => $value) {
            if ($lifetime > 0) {
                $_SESSION["$this->namespace-expires"][$id] = time() + $lifetime;
            }
            $_SESSION[$this->namespace][$id] = $value;
        }

        return true;
    }
}