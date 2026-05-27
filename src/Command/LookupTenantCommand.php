<?php

namespace App\Command;

use App\Sharding\Contract\ShardRegistryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * LookupTenantCommand
 *
 * RESPONSIBILITY:
 * - Find tenant shard mapping
 *
 * EXAMPLE:
 * php bin/console app:tenant:lookup tenant-1
 */
#[AsCommand(name: 'app:tenant:lookup')]
final class LookupTenantCommand extends Command
{
    public function __construct(
        private readonly ShardRegistryInterface $registry
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'tenantId',
            InputArgument::REQUIRED
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $tenantId = $input->getArgument('tenantId');

        $shard = $this->registry
            ->getShardForTenant($tenantId);

        if ($shard === null) {
            $output->writeln('Tenant not found');

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            'Tenant %s → shard %s (%s / %s)',
            $tenantId,
            $shard->id,
            $shard->type,
            $shard->region
        ));

        return Command::SUCCESS;
    }
}