<?php

namespace Infira\Cachly\options;

use Infira\Cachly\Cachly;

class FileDriverOptions
{
	public $fallbackDriver = Cachly::SESS;
	public $cachePath      = null;
}