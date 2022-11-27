<?php

namespace Infira\Cachly\options;


abstract class AdapterOptions
{
    private array $opts;

    public function __construct(
        array $options = [

            /**
             * the default lifetime (in seconds) for cache items that do not define their
             * own lifetime, with a value 0 causing items to be stored indefinitely (i.e.
             * until the files are deleted)
             */
            'defaultLifeTime' => 0
        ]
    ) {
        $this->opts = $options;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->opts);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->opts[$key];
    }

    public function getDefaultLifeTime(int $default = 0)
    {
        return $this->get('defaultLifeTime', $default);
    }

    public function getOptions(): array
    {
        return $this->get('options', []);
    }
}