<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Command;

use Nowo\BreadcrumbKitBundle\Service\BreadcrumbTrailPreview;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'nowo_breadcrumb_kit:preview',
    description: 'Preview the breadcrumb trail for a synthetic request',
)]
final class PreviewBreadcrumbTrailCommand extends Command
{
    public function __construct(
        private readonly BreadcrumbTrailPreview $preview,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addOption('collection', null, InputOption::VALUE_REQUIRED, 'Collection code', 'default')
            ->addOption('context-key', null, InputOption::VALUE_REQUIRED, 'Context key', '')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Request path', '/')
            ->addOption('route', null, InputOption::VALUE_REQUIRED, 'Symfony route name (_route)')
            ->addOption('route-params', null, InputOption::VALUE_REQUIRED, 'JSON object of _route_params', '{}')
            ->addOption('attributes', null, InputOption::VALUE_REQUIRED, 'JSON object of extra request attributes', '{}')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Request locale');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $routeParams = $this->decodeMap((string) $input->getOption('route-params'));
            $attributes = $this->decodeMap((string) $input->getOption('attributes'));
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $route = $input->getOption('route');
        $locale = $input->getOption('locale');
        $view = $this->preview->preview(
            (string) $input->getOption('collection'),
            (string) $input->getOption('context-key'),
            (string) $input->getOption('path'),
            \is_string($route) && '' !== $route ? $route : null,
            $routeParams,
            $attributes,
            'GET',
            \is_string($locale) && '' !== $locale ? $locale : null,
        );

        if ([] === $view->nodes) {
            $io->warning('Empty trail (no match or empty collection presentation).');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($view->nodes as $i => $node) {
            $rows[] = [
                (string) $i,
                $node->label,
                $node->url ?? '(none)',
                $node->current ? 'yes' : 'no',
                $node->icon ?? '',
            ];
        }
        $io->table(['#', 'label', 'url', 'current', 'icon'], $rows);

        return Command::SUCCESS;
    }

    /**
     * @return array<string, scalar|null>
     */
    private function decodeMap(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Invalid JSON: '.$e->getMessage(), 0, $e);
        }
        if (!\is_array($decoded)) {
            throw new \InvalidArgumentException('Expected a JSON object.');
        }
        $out = [];
        foreach ($decoded as $k => $v) {
            if (!\is_string($k)) {
                continue;
            }
            if (null === $v || \is_scalar($v)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}
