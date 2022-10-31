<?php

namespace Infira\Cachly\Item;


use Symfony\Component\Cache\Adapter\AbstractAdapter;

/**
 * @method ArrayItem set(array $newArray)
 */
class ArrayItem extends CacheItem
{
    public function __construct(private readonly AbstractAdapter $adapter, string|int $key, array $array = [])
    {
        parent::__construct($this->adapter, $key, ($array ?: []));
    }

    public function add(mixed $value): static
    {
        $array = $this->get();
        $array[] = $value;
        $this->set($array);

        return $this;
    }

    public function getItem(string|int $key): mixed
    {
        return $this->get()[$key];
    }

    public function put(string|int $key, mixed $value): static
    {
        $array = $this->get();
        $array[$key] = $value;
        $this->set($array);

        return $this;
    }

    public function has(string|int $key): bool
    {
        return array_key_exists($key, $this->get());
    }

    public function forget(string|int $key): static
    {
        if($this->has($key)) {
            $array = $this->get();
            unset($array[$key]);
            $this->set($array);
        }

        return $this;
    }

    public function all(): array
    {
        return $this->get();
    }
}