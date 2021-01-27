<?php

namespace Experteam\ApiRedisBundle\Message;

use Exception;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

abstract class MessageSerializer implements SerializerInterface
{
    /**
     * @var DecoderInterface
     */
    protected $decoder;

    /**
     * @var EncoderInterface
     */
    protected $encoder;

    /**
     * @param DecoderInterface $decoder
     * @param EncoderInterface $encoder
     */
    public function __construct(DecoderInterface $decoder, EncoderInterface $encoder)
    {
        $this->decoder = $decoder;
        $this->encoder = $encoder;
    }

    /**
     * @param array $encodedEnvelope
     * @return Envelope
     * @throws Exception
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        $data = [];
        $messageClass = $this->getMessageClass();

        if (empty($messageClass))
            throw new Exception(sprintf('Empty message class. Please redefine the getMessageClass function in %s', get_class($this)));

        if (!class_exists($messageClass))
            throw new Exception(sprintf('Message class "%s" does not exist', $messageClass));

        if (isset($encodedEnvelope['body'])) {
            $body = $this->decoder->decode($encodedEnvelope['body'], 'json');

            if (isset($body['data'])) {
                $data = $body['data'];
            }
        }

        return new Envelope(new $messageClass($data));
    }

    /**
     * @param Envelope $envelope
     * @return array
     * @throws Exception
     */
    public function encode(Envelope $envelope): array
    {
        $messageClass = $this->getMessageClass();

        if (empty($messageClass))
            throw new Exception(sprintf('Empty message class. Please redefine the getMessageClass function in %s', get_class($this)));

        if (!class_exists($messageClass))
            throw new Exception(sprintf('Message class "%s" does not exist', $messageClass));

        $body = '';
        $message = $envelope->getMessage();

        if ($message instanceof $messageClass) {
            $body = $this->encoder->encode(['data' => $message->getData()], 'json');
        }

        return ['body' => $body];
    }

    /**
     * @return string|null
     */
    public function getMessageClass()
    {
        return null;
    }
}