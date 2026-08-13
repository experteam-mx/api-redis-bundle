<?php

namespace Experteam\ApiRedisBundle\Command;

use Experteam\ApiRedisBundle\Service\RedisClient\RedisClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'experteam:redis:ping', description: 'Test redis connection')]
class RedisPingCommand extends Command
{
    public function __construct(private readonly RedisClientInterface $redisClient)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('stream-compute',
            's',
            InputOption::VALUE_NONE,
            'Ping to stream-compute redis server'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connections = ['default' => [false]];
        if ($input->getOption('stream-compute')) {
            $connections['redis_stream_compute'] = [true];
        }

        $ok = true;
        foreach ($connections as $label => [$isStreamCompute]) {
            [$error, $message] = $this->redisClient->command('ping', [], $isStreamCompute);
            $ok = $ok && !$error;
            $output->writeln(sprintf(
                '<%s>[%s] %s</%s>',
                $error ? 'error' : 'info',
                $label,
                $error ? $message : 'PONG',
                $error ? 'error' : 'info'
            ));
        }

        $output->writeln($ok ? '<info>Connection OK</info>' : '<error>Connection FAILED</error>');
        return $ok ? Command::SUCCESS : Command::FAILURE;
    }
}
