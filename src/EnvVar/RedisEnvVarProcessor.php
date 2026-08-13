<?php

namespace Experteam\ApiRedisBundle\EnvVar;

use Closure;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

class RedisEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, Closure $getEnv): ?array
    {
        if ($prefix === 'redis_ssl') {
            $mode = $getEnv("default::$name") ?? 'default';

            return $mode === 'secure'
                ? ['cafile' => $getEnv('SNI_REDIS_CA_FILE')]
                : null;
        }

        return null;
    }

    public static function getProvidedTypes(): array
    {
        return [
            'redis_ssl' => 'array',
        ];
    }
}