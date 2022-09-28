<?php

namespace Experteam\ApiRedisBundle\MessageSerializer;

use Experteam\ApiRedisBundle\Message\FailedMessage;
use Experteam\ApiRedisBundle\Util\Literal;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;

class FailedMessageSerializer extends MessageSerializer
{
    /**
     * @param array $encodedEnvelope
     * @return Envelope
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        $data = [];
        $class = $exceptionClass = $exceptionMessage = '';

        if (isset($encodedEnvelope[Literal::BODY])) {
            $body = $this->decoder->decode($encodedEnvelope[Literal::BODY], 'json');

            if (isset($body[Literal::DATA])) {
                $data = $body[Literal::DATA];
            }

            if (isset($body[Literal::L_CLASS])) {
                $class = $body[Literal::L_CLASS];
            }

            if (isset($body[Literal::EXCEPTION_CLASS])) {
                $exceptionClass = $body[Literal::EXCEPTION_CLASS];
            }

            if (isset($body[Literal::EXCEPTION_MESSAGE])) {
                $exceptionMessage = $body[Literal::EXCEPTION_MESSAGE];
            }
        }

        return new Envelope(new FailedMessage($data, $class, $exceptionClass, $exceptionMessage));
    }

    /**
     * @param Envelope $envelope
     * @return array
     */
    public function encode(Envelope $envelope): array
    {
        $data = [];
        $message = $envelope->getMessage();
        $class = get_class($message);
        $exceptionClass = $exceptionMessage = '';
        /** @var ErrorDetailsStamp|null $errorDetailsStamp */
        $errorDetailsStamp = $envelope->last(ErrorDetailsStamp::class);
        $flattenException = (!is_null($errorDetailsStamp) ? $errorDetailsStamp->getFlattenException() : null);

        if (!is_null($flattenException)) {
            $exceptionClass = $flattenException->getClass();
            $exceptionMessage = $flattenException->getMessage();
        }

        if (method_exists($message, 'getData')) {
            $data = $message->getData();
        }

        return [Literal::BODY => $this->encoder->encode([
            Literal::L_CLASS => $class,
            Literal::DATA => $data,
            Literal::EXCEPTION_CLASS => $exceptionClass,
            Literal::EXCEPTION_MESSAGE => $exceptionMessage
        ], 'json')];
    }
}
