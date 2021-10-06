<?php

namespace Experteam\ApiRedisBundle\Command;

use Exception;
use Experteam\ApiRedisBundle\Service\RedisClient\RedisClientInterface;
use Experteam\ApiRedisBundle\Service\RedisTransport\RedisTransportInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CheckDataCommand extends Command
{
    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;

    /**
     * @var RedisClientInterface
     */
    protected $redisClient;

    /**
     * @var RedisTransportInterface
     */
    protected $redisTransport;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(ParameterBagInterface $parameterBag, RedisClientInterface $redisClient, RedisTransportInterface $redisTransport, LoggerInterface $logger)
    {
        $this->parameterBag = $parameterBag;
        $this->redisClient = $redisClient;
        $this->redisTransport = $redisTransport;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('experteam:redis:check:data')
            ->setDescription('Check the Redis keys and refresh the data if they don\'t exist')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ui = new SymfonyStyle($input, $output);
        $ui->text("<info>> Check Redis keys.</info>");
        $cfgEntities = $this->parameterBag->get('experteam_api_redis.entities');
        $appPrefix = $this->parameterBag->get('app.prefix');

        $entitiesToRestore = [];
        foreach ($cfgEntities as $class => $config) {
            $key = "$appPrefix.{$config['prefix']}";
            $missing = empty($this->redisClient->keys($key));

            $ui->text(sprintf('%s => %s', $key, $missing ? 'MISSING' : 'OK'));

            if ($missing)
                $entitiesToRestore[] = $class;
        }

        if (count($entitiesToRestore) > 0) {
            $this->redisTransport->restoreData($entitiesToRestore);
            $this->logger->info('Missing Redis data restored', ['entities' => $entitiesToRestore]);
            $ui->text("<info>> Missing Redis data restored</info>");
        } else
            $ui->text("<info>> Nothing to restore</info>");

        return Command::SUCCESS;
    }
}