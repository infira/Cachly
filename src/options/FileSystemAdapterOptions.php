<?php

namespace Infira\Cachly\options;

/**
 * @see
 */
class FileSystemAdapterOptions extends AdapterOptions
{
    public ?string $directory = null;

    /**
     * @param  array  $options
     * @see https://symfony.com/doc/current/components/cache/adapters/filesystem_adapter.html
     */
    public function __construct(
        array $options = [
            /*
             * the main cache directory (the application needs read-write permissions on it)
             * if none is specified, a directory is created inside the system temporary directory
             */
            'directory' => null,

            /**
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