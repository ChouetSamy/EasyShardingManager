<?php

namespace App\Command;

use App\Sharding\Model\Shard;
use App\Sharding\Contract\ShardRegistryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CreateShardCommand
 *
 * RESPONSIBILITY:
 * - Register a shard in registry
 *
 * EXAMPLE:
 * php bin/console app:shard:create shard-eu-1 cockroach eu
 */
#[AsCommand(name: 'app:shard:create')]
final class CreateShardCommand extends Command
{
    public function __construct(
        private readonly ShardRegistryInterface $registry
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED)
            ->addArgument('type', InputArgument::REQUIRED)
            ->addArgument('region', InputArgument::REQUIRED);
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $shard = new Shard(
            id: $input->getArgument('id'),
            type: $input->getArgument('type'),
            region: $input->getArgument('region')
        );

        $this->registry->registerShard($shard);

        $output->writeln(
            sprintf(
                'Shard created: %s (%s / %s)',
                $shard->id,
                $shard->type,
                $shard->region
            )
        );

        return Command::SUCCESS;
    }
}