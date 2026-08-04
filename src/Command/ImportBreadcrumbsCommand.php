<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Command;

use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'nowo_breadcrumb_kit:import',
    description: 'Import breadcrumb JSON from a file (same shape as dashboard export)',
)]
final class ImportBreadcrumbsCommand extends Command
{
    public function __construct(
        private readonly BreadcrumbImporter $importer,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to JSON file')
            ->addOption(
                'strategy',
                null,
                InputOption::VALUE_REQUIRED,
                'skip_existing|replace',
                BreadcrumbImporter::STRATEGY_SKIP_EXISTING,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getOption('file');
        if (!\is_string($file) || '' === $file || !is_readable($file)) {
            $io->error('Provide a readable --file path.');

            return Command::FAILURE;
        }

        $raw = file_get_contents($file);
        if (false === $raw) {
            $io->error('Could not read file.');

            return Command::FAILURE;
        }

        try {
            $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $io->error('Invalid JSON: '.$e->getMessage());

            return Command::FAILURE;
        }
        if (!\is_array($decoded)) {
            $io->error('JSON root must be an object or list.');

            return Command::FAILURE;
        }

        $strategy = (string) $input->getOption('strategy');
        if (!\in_array($strategy, [BreadcrumbImporter::STRATEGY_SKIP_EXISTING, BreadcrumbImporter::STRATEGY_REPLACE], true)) {
            $io->error('strategy must be skip_existing or replace.');

            return Command::FAILURE;
        }

        $result = $this->importer->import($decoded, $strategy);
        $io->table(
            ['created', 'updated', 'skipped', 'errors'],
            [[(string) $result['created'], (string) $result['updated'], (string) $result['skipped'], (string) \count($result['errors'])]],
        );
        foreach ($result['errors'] as $err) {
            $io->warning($err);
        }

        return [] === $result['errors'] ? Command::SUCCESS : Command::FAILURE;
    }
}
