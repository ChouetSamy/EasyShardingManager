<?php

namespace App\Command;

use App\Seed\SeedCockroachScript;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed:cockroach',
    description: 'Seed CockroachDB with fake users'
)]
final class SeedCockroachCommand extends Command
{
    public function __construct(
        private readonly SeedCockroachScript $script
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'count',
            InputArgument::OPTIONAL,
            'Number of users',
            100000
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {

        $count = (int) $input->getArgument('count');

        $dsn = 'postgresql://root@cockroachdb:26257/defaultdb?sslmode=disable';

        $this->script->run($dsn, $count);

        return Command::SUCCESS;
    }
}