<?php

namespace Infira\Cachly;

use Infira\Cachly\Exception\InvalidArgumentException;
use stdClass;
use Symfony\Component\Cache\Adapter\AbstractAdapter;

class AdaptersCollection
{
    /**
     * @var stdClass[]
     */
    private array $storage = [];

    public function get(string $name, string $namespace): AbstractAdapter
    {
        if(!$this->isRegistered($name)) {
            throw new InvalidArgumentException("adapter named($name) is not registered");
        }
        $adapter = &$this->storage[$name];

        if($adapter->isConstructed) {
            return $adapter->adapter;
        }
        $adapter->isConstructed = true;
        $adapter->adapter = ($adapter->constructor instanceof AbstractAdapter) ? $adapter->constructor : ($adapter->constructor)($namespace);

        return $adapter->adapter;
    }

    public function isConstructed(string $name): bool
    {
        if(!isset($this->storage[$name])) {
            return false;
        }

        return $this->storage[$name]->isConstructed;
    }

    /**
     * @param string $name
     * @param callable|string $constructor
     */
    public function register(string $name, callable|string $constructor): void
    {
        if(isset($this->storage[$name])) {
            throw new InvalidArgumentException("adapter($name) is already registered");
        }
        $this->storage[$name] = (object)['constructor' => $constructor, 'isConstructed' => false, 'adapter' => null];
    }

    public function isRegistered(string $name): bool
    {
        return array_key_exists($name, $this->storage);
    }
}