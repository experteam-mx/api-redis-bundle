<?php

namespace Experteam\ApiRedisBundle\Command;

use Exception;
use Experteam\ApiRedisBundle\Service\RedisTransportV2\RedisTransportV2Interface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RefreshCommand extends Command
{
    /**
     * @var RedisTransportV2Interface
     */
    protected $redisTransport;

    public function __construct(RedisTransportV2Interface $redisTransport)
    {
        $this->redisTransport = $redisTransport;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('experteam:redis:refresh')
            ->setDescription('Refresh Redis data and queues')
            ->addOption('save', null, InputOption::VALUE_NONE, 'Refresh data structures')
            ->addOption('message', null, InputOption::VALUE_NONE, 'Refresh message queues')
            ->addOption('stream_compute', null, InputOption::VALUE_NONE, 'Refresh stream compute queues')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Created at from datetime. Format 2000-01-01T00:00:00')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Created at to datetime. Format 2000-01-01T00:00:00')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Entity to refresh')
            ->addOption('id', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Id to refresh');
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
        $from = $input->getOption('from');
        $to = $input->getOption('to');
        $ids = $input->getOption('id');

        $namespace = "App\\Entity\\";

        $entities = array_map(function (&$entity) use ($namespace) {
            return strpos($entity, $namespace) === false ? $namespace . $entity : $entity;
        }, $input->getOption('entity'));

        if ($input->getOption('save')) {
            $ui->text("<info>> Refreshing data structures...</info>");
            $keysNotGenerated = $this->redisTransport->restoreData($entities);

            if (!is_null($keysNotGenerated)) {
                $ui->error('The following redis keys were not generated => "' . implode(', ', $keysNotGenerated) . '".');
                return Command::FAILURE;
            }
        }

        if ($input->getOption('message')) {
            $ui->text("<info>> Refreshing message queues...</info>");

            if (is_null($from)) {
                $ui->error("<error>The --from option es required for --message</error>");
                return Command::FAILURE;
            }

            $this->redisTransport->restoreMessages($from, $to, $entities, $ids);
        }

        if ($input->getOption('stream_compute')) {
            $ui->text("<info>> Refreshing stream compute queues...</info>");
            $this->redisTransport->restoreStreamCompute($from, $to, $entities, $ids);
        }

        return Command::SUCCESS;
    }
}
