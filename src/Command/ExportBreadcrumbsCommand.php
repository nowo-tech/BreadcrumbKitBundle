<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Command;

use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'nowo_breadcrumb_kit:export',
    description: 'Export breadcrumb collections to JSON (stdout or --file)',
)]
final class ExportBreadcrumbsCommand extends Command
{
    public function __construct(
        private readonly BreadcrumbExporter $exporter,
        private readonly BreadcrumbCollectionRepository $collectionRepository,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Write JSON to this path instead of stdout')
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'Export a single collection code (optional)')
            ->addOption('context-key', null, InputOption::VALUE_REQUIRED, 'Context key when using --code', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $code = $input->getOption('code');
        if (\is_string($code) && '' !== $code) {
            $ctx = (string) $input->getOption('context-key');
            $collection = $this->collectionRepository->findOneByCodeAndContextKey($code, $ctx);
            if (null === $collection) {
                $io->error(\sprintf('Collection "%s" (context "%s") not found.', $code, $ctx));

                return Command::FAILURE;
            }
            $payload = $this->exporter->exportCollection($collection);
        } else {
            $payload = $this->exporter->exportAll();
        }

        try {
            $json = json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $io->error('JSON encode failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        $file = $input->getOption('file');
        if (\is_string($file) && '' !== $file) {
            $fs = new Filesystem();
            $fs->dumpFile($file, $json."\n");
            $io->success(\sprintf('Exported to %s', $file));

            return Command::SUCCESS;
        }

        $output->writeln($json);

        return Command::SUCCESS;
    }
}
