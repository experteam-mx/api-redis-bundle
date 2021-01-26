<?php

namespace Experteam\ApiRedisBundle\Message;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

abstract class MessageHandler implements MessageHandlerInterface
{
    /**
     * @var EntityManagerInterface
     */
    protected $manager;

    /**
     * @var DecoderInterface
     */
    protected $decoder;

    /**
     * @param EntityManagerInterface $manager
     * @param DecoderInterface $decoder
     */
    public function __construct(EntityManagerInterface $manager, DecoderInterface $decoder)
    {
        $this->manager = $manager;
        $this->decoder = $decoder;
    }

    /**
     * @param Message $message
     */
    public function __invoke(Message $message)
    {
        $this->processData($this->decoder->decode($message->getData(), 'json'));
    }

    /**
     * @param array $data
     */
    public function processData(array $data)
    {
        // Redefine this function to process data
    }

}