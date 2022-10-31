<?php

namespace Infira\Cachly\Adapter;

use RuntimeException;
use Symfony\Component\Cache\Adapter\AbstractAdapter;

class SessionAdapter extends AbstractAdapter
{
    public function __construct(private readonly string $namespace = '')
    {
        if(!isset($_SESSION)) {
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

        foreach($ids as $id) {
            if($this->doHave($id)) {
                $values[$id] = $_SESSION[$this->namespace][$id];
            }
        }

        return $values;
    }

    protected function doHave(string $id): bool
    {
        if(!array_key_exists($this->namespace, $_SESSION)) {
            return false;
        }

        return array_key_exists($id, $_SESSION[$this->namespace]);
    }

    protected function doClear(string $namespace): bool
    {
        $_SESSION[$this->namespace] = [];

        return true;
    }

    protected function doDelete(array $ids): bool
    {
        foreach($ids as $id) {
            if($this->doHave($id)) {
                unset($_SESSION[$this->namespace][$id]);
            }
        }

        return true;
    }

    protected function doSave(array $values, int $lifetime): array|bool
    {
        foreach($values as $id => $value) {
            $_SESSION[$this->namespace][$id] = $value;
        }

        return true;
    }
}