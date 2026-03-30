<?php

namespace Infira\Cachly\options;

class MemcachedAdapterOptions extends AdapterOptions
{
    /**
     * @param array $options
     * @see https://symfony.com/doc/current/components/cache/adapters/memcached_adapter.html#configure-the-connection
     */
    public function __construct(
        array $options = [
            'dsn' => "memcached://my.server.com:11211",

            /**
             * @see https://symfony.com/doc/current/components/cache/adapters/memcached_adapter.html#configure-the-options
             */
            'options' => [],

            /*
             * the default lifetime (in seconds) for cache items that do not define their
             * own lifetime, with a value 0 causing items to be stored indefinitely (i.e.
             * until the files are deleted)
             */
            'defaultLifeTime' => 0
        ]
    ) {
        parent::__construct($options);
    }
}