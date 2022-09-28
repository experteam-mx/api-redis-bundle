<?php

namespace Experteam\ApiRedisBundle\Message;

class FailedMessage extends Message
{
    /**
     * @var string
     */
    private $class;

    /**
     * @var string
     */
    private $exceptionClass;

    /**
     * @var string
     */
    private $exceptionMessage;

    /**
     * @param mixed $data
     * @param string $class
     * @param string $exceptionClass
     * @param string $exceptionMessage
     */
    public function __construct($data, string $class, string $exceptionClass, string $exceptionMessage)
    {
        parent::__construct($data);
        $this->class = $class;
        $this->exceptionClass = $exceptionClass;
        $this->exceptionMessage = $exceptionMessage;
    }

    /**
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return string
     */
    public function getExceptionClass(): string
    {
        return $this->exceptionClass;
    }

    /**
     * @return string
     */
    public function getExceptionMessage(): string
    {
        return $this->exceptionMessage;
    }
}
