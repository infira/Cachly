<?php

namespace Infira\Cachly;

class CachlyException extends \Exception
{
    private $_data;

    public function __construct($message, $data)
    {
        $this->_data = $data;
        parent::__construct($message);
    }

    public function getData()
    {
        return $this->_data;
    }
}