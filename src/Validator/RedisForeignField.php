<?php

namespace Experteam\ApiRedisBundle\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[\Attribute]
class RedisForeignField extends Constraint
{
    public string|array $key;
    public string $message;

    public function __construct(
        string|array $key = '',
        string       $message = '',
        array        $options = [],
        ?array       $groups = null,
        mixed        $payload = null
    )
    {
        if (is_array($key)) {
            $options = array_merge($key, $options);
            $key = ($options['key'] ?? '');
            $message = ($options['message'] ?? '');
        }

        parent::__construct($options, $groups, $payload);
        $this->key = $key;
        $this->message = $message;
    }
}
