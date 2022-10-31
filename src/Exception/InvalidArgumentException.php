<?php

namespace Infira\Cachly\Exception;

use Psr\Cache\InvalidArgumentException as Psr6CacheInterface;

class InvalidArgumentException extends \InvalidArgumentException implements Psr6CacheInterface
{
}