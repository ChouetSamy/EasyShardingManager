<?php

namespace App\Command;

use App\Sharding\Strategy\PartitionEngine;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * AssignTenantCommand
 *
 * RESPONSIBILITY:
 * - Assign a tenant automatically
 * - Uses PartitionEngine
 *
 * EXAMPLE:
 * php bin/console app:tenant:assign tenant-1
 */
#[AsCommand(name: 'app:tenant:assign')]
final class AssignTenantCommand extends Command
{
    public function __construct(
        private readonly PartitionEngine $partitionEngine
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

        $shard = $this->partitionEngine
            ->assignTenant($tenantId);

        $output->writeln(
            sprintf(
                'Tenant %s assigned to shard %s',
                $tenantId,
                $shard->id
            )
        );

        return Command::SUCCESS;
    }
}