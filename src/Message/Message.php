<?php

namespace Experteam\ApiRedisBundle\Message;

abstract class Message
{
    /**
     * @var mixed
     */
    private $data;

    /**
     * @param mixed $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function getConditions(): array
    {
        return [];
    }
}