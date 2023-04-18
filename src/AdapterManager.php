<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Infira\Cachly;

use DateTimeInterface;
use Infira\Cachly\Item\ArrayItem;
use Infira\Cachly\Item\CacheItem;
use Infira\Cachly\Support\Collection;
use Infira\Cachly\Support\Helpers;
use stdClass;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Wolo\Date\Date;

class AdapterManager
{
    public ArrayItem $keys;

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function __construct(protected string $namespace, protected AbstractAdapter $adapter)
    {
        $this->keys = new ArrayItem($this->adapter, "$namespace-keys");
    }

    public function collection(): Collection
    {
        return new Collection($this->all());
    }

    public function getValue(string $id, mixed $default = null): mixed
    {
        if (!$this->has($id)) {
            return $default;
        }

        return $this->item($id)->get()->v;
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function get(string $id, callable $callback, float $beta = null, array &$metadata = null): mixed
    {
        return $this->adapter->get(
            $id,
            fn(\Symfony\Component\Cache\CacheItem $item) => $callback($this->item($id)),
            $beta,
            $metadata
        );
    }

    public function all(): array
    {
        $output = [];
        foreach ($this->getKeys() as $id => $key) {
            if ($this->has($id)) {
                $output[$key] = $this->getValue($id);
            }
        }

        return $output;
    }

    /**
     * @return array - [[$id => $key]]
     */
    public function getKeys(): array
    {
        return $this->keys->all();
    }

    public function registerKey(string $key, string $id): static
    {
        if (!$this->keys->has($id)) {
            $this->keys->put($id, $key);
        }

        return $this;
    }

    public function put(string $id, mixed $value, int|string $expires = 0): bool
    {
        if (!$this->keys->has($id)) {
            throw new InvalidArgumentException("id($id) key is not registered, use ".'$this->registerKey($id,$key)'." to register");
        }
        $expiresIn = 0;

        if (is_numeric($expires) && !empty($expires)) {
            $expiresIn = (int)$expires;
        }
        elseif (is_string($expires) && !empty($expires)) {
            if ($expires[0] !== '+') {
                $expires = "+$expires";
            }
            $expiresIn = strtotime($expires);
        }

        $node = new stdClass();
        $node->v = $value;
        $node->t = $expiresIn;

        $item = $this->item($id);
        if ($expiresIn !== 0) {
            $item->expiresAt(Date::of($expires)->getDriver());
        }

        return $item->set($node)->save(function () {
            $this->keys->save();
        });
    }

    /**
     * Renamed to put()
     *
     * @param  string  $id
     * @param  mixed  $value
     * @param  int|string  $expires
     * @return bool
     * @see self::putValue()
     * @deprecated
     */
    public function putValue(string $id, mixed $value, int|string $expires = 0): bool
    {
        return $this->put(...func_get_args());
    }

    public function has(string $id): bool
    {
        return !$this->isExpired($id);
    }

    public function isExpired(string $id): bool
    {
        if (!$this->keys->has($id)) {
            return true;
        }
        $r = $this->expiration($id);
        if ($r === -1) {
            return true;
        }

        if ($r === 0) {
            return false;
        }

        if (time() > $r) {
            return true;
        }

        return false;
    }

    public function expiration(string $id): int|string|null
    {
        if (!$this->keys->has($id)) {
            throw new InvalidArgumentException("$this->namespace does not have id('$id)'");
        }
        $node = $this->item($id)->get();
        if ($node->t > 0 && time() > $node->t) {
            return -1;
        }

        return $node->t;
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function forget(string $id): bool
    {
        if (!$this->keys->has($id)) {
            return true;
        }

        return $this->item($id)->destroy(fn() => $this->keys->forget($id)->save());
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function clear(): void
    {
        //$this->adapter->deleteItems(array_keys($this->getKeys()));
        $this->adapter->clear($this->namespace);
        $this->keys->destroy();
    }

    public function item(string|int $key): CacheItem
    {
        return Helpers::once($this->namespace, $key, fn() => new CacheItem($this->adapter, $key, null));
    }
}