<?php

namespace Experteam\ApiRedisBundle\Validator;

use Experteam\ApiRedisBundle\Service\RedisClient\RedisClientInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class RedisForeignFieldValidator extends ConstraintValidator
{
    public function __construct(
        private readonly RedisClientInterface $redisClient
    )
    {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof RedisForeignField) {
            throw new UnexpectedTypeException($constraint, RedisForeignField::class);
        }

        if (empty($value)) {
            return;
        }

        $result = $this->redisClient->hget($constraint->key, $value);

        if (!isset($result)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
