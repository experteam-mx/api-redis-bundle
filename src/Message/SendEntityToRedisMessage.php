<?php

namespace Experteam\ApiRedisBundle\Message;

class SendEntityToRedisMessage
{
    private string $class;
    private int $id;

    public function __construct(string $class, int $id)
    {
        $this->class = $class;
        $this->id = $id;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getId(): int
    {
        return $this->id;
    }
}
