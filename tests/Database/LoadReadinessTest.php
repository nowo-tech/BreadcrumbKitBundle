<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Database;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\EventSubscriber\StorageFailureSubscriber;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use Nowo\BreadcrumbKitBundle\Tests\Support\BreadcrumbStore;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * 2.20 (Beacon spec 239): lock waits and concurrent processes on a real MySQL / PostgreSQL server. Skipped without
 * BREADCRUMB_TEST_DATABASE_URL (the CI "Load readiness" job runs them with fail-on-skipped).
 */
final class LoadReadinessTest extends TestCase
{
    private BreadcrumbStore $store;

    /** @var list<resource> */
    private array $processes = [];

    protected function setUp(): void
    {
        $url = BreadcrumbStore::serverUrl();
        if (null === $url) {
            self::markTestSkipped('Set BREADCRUMB_TEST_DATABASE_URL to run the real-server tests.');
        }
        $this->store = new BreadcrumbStore($url);
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if (\is_resource($process)) {
                proc_close($process);
            }
        }
    }

    public function testALockWaitTimeoutIsATransientFailureNotARawDriverError(): void
    {
        $this->store->importer()->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'old']]]);
        $id = (int) $this->store->connection()->fetchOne('SELECT id FROM dashboard_breadcrumb_collection');
        $peer = $this->start(['hold', (string) $id]);
        self::assertSame('READY', $this->line($peer));
        $this->store->connection()->executeStatement($this->store->connection()->getDatabasePlatform() instanceof AbstractMySQLPlatform ? 'SET SESSION innodb_lock_wait_timeout = 1' : "SET lock_timeout = '1s'");

        try {
            $this->store->importer()->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'new']]], BreadcrumbImporter::STRATEGY_REPLACE);
            self::fail('The collection row is locked by the peer.');
        } catch (TransientStorageFailure $failure) {
            self::assertNotNull($failure->retryAfterSeconds());
            // 2.23.0 (REQ-RUNTIME-003 rule 6): the real failure of this server is answered 409 + Retry-After + CONFLICT_001.
            $request = new Request();
            $request->attributes->set('_route', 'nowo_breadcrumb_kit_dashboard_import');
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
            $event = new ExceptionEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $failure);
            (new StorageFailureSubscriber())->onKernelException($event);
            $response = $event->getResponse();
            self::assertNotNull($response);
            self::assertSame(409, $response->getStatusCode());
            self::assertSame('1', $response->headers->get('Retry-After'));
            self::assertSame('CONFLICT_001', json_decode((string) $response->getContent(), true)['code']);
        } finally {
            $this->release($peer);
        }

        self::assertSame(0, $this->store->connection()->getTransactionNestingLevel(), 'the worker keeps a usable connection');
        self::assertSame(['old'], $this->routes('main'), 'nothing of the failed import survived');
        $this->store->entityManager->clear();
        $result = $this->store->importer()->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => 'new']]], BreadcrumbImporter::STRATEGY_REPLACE);
        self::assertSame(1, $result['updated'], 'and works again once the peer committed');
    }

    public function testTwoProcessesReplacingOneCollectionKeepOneWholeTree(): void
    {
        $this->store->importer()->import(['collection' => ['code' => 'shared'], 'items' => [['routeName' => 'seed']]]);
        $outputs = $this->race([['import-same', 'shared', '40', BreadcrumbImporter::STRATEGY_REPLACE, 'a'], ['import-same', 'shared', '40', BreadcrumbImporter::STRATEGY_REPLACE, 'b']]);

        foreach ($outputs as $lines) {
            self::assertSame(['ok' => 40], array_count_values($lines), 'every import succeeded');
        }
        $routes = $this->routes('shared');
        self::assertCount(10, $routes, 'one tree of ten items, never two interleaved or doubled');
        self::assertCount(1, array_unique(array_map(static fn (string $route): string => $route[0], $routes)), 'all from the same import');
    }

    public function testTwoProcessesCreatingTheSameKeysBothSucceed(): void
    {
        $outputs = $this->race([['import-loop', 'k', '30', BreadcrumbImporter::STRATEGY_REPLACE, 'a'], ['import-loop', 'k', '30', BreadcrumbImporter::STRATEGY_REPLACE, 'b']]);

        foreach ($outputs as $lines) {
            self::assertSame(['ok' => 30], array_count_values($lines), 'no unique violation reaches a caller');
        }
        self::assertSame(30, (int) $this->store->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_collection'));
        self::assertSame(300, (int) $this->store->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_breadcrumb_item'), 'ten items per collection: the loser replaced the winner\'s tree');
    }

    public function testCollectionsImportedInOppositeOrdersDoNotDeadlock(): void
    {
        $this->store->importer()->import(['collections' => [
            ['collection' => ['code' => 'x'], 'items' => [['routeName' => 'seed']]],
            ['collection' => ['code' => 'y'], 'items' => [['routeName' => 'seed']]],
        ]]);
        $xy = ['collections' => [['collection' => ['code' => 'x'], 'items' => [['routeName' => 'a']]], ['collection' => ['code' => 'y'], 'items' => [['routeName' => 'a']]]]];
        $yx = ['collections' => [['collection' => ['code' => 'y'], 'items' => [['routeName' => 'b']]], ['collection' => ['code' => 'x'], 'items' => [['routeName' => 'b']]]]];
        $this->store->importer()->import($xy, BreadcrumbImporter::STRATEGY_REPLACE);
        $this->store->importer()->import($yx, BreadcrumbImporter::STRATEGY_REPLACE);

        self::assertSame(['b'], $this->routes('x'));
        self::assertSame(['b'], $this->routes('y'));
        self::assertSame(0, $this->store->connection()->getTransactionNestingLevel());
    }

    /**
     * @param list<list<string>> $commands
     *
     * @return list<list<string>> the output lines of each worker
     */
    private function race(array $commands): array
    {
        $workers = [];
        foreach ($commands as $command) {
            $workers[] = $this->start($command);
        }
        foreach ($workers as $worker) {
            self::assertSame('READY', $this->line($worker));
        }
        foreach ($workers as $worker) {
            fwrite($worker['pipes'][0], "GO\n");
        }
        $outputs = [];
        foreach ($workers as $worker) {
            $outputs[] = array_values(array_filter(array_map(\trim(...), explode("\n", (string) stream_get_contents($worker['pipes'][1])))));
        }

        return $outputs;
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{pipes: array<int, resource>}
     */
    private function start(array $arguments): array
    {
        $command = [\PHP_BINARY, __DIR__.'/Support/worker.php', (string) BreadcrumbStore::serverUrl(), ...$arguments];
        // @phpstan-ignore frankenphp.classic.noUnlimitedIoTimeout (a test worker: the CI job has a step timeout)
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => \STDERR], $pipes);
        self::assertIsResource($process);
        $this->processes[] = $process;

        return ['pipes' => $pipes];
    }

    /**
     * @param array{pipes: array<int, resource>} $worker
     */
    private function line(array $worker): string
    {
        return trim((string) fgets($worker['pipes'][1]));
    }

    /**
     * @param array{pipes: array<int, resource>} $worker
     */
    private function release(array $worker): void
    {
        fwrite($worker['pipes'][0], "release\n");
    }

    /**
     * @return list<string>
     */
    private function routes(string $code): array
    {
        $this->store->entityManager->clear();
        $collection = $this->store->collections->findOneByCodeAndContextKey($code);
        self::assertInstanceOf(BreadcrumbCollection::class, $collection);

        return array_map(static fn ($item): string => $item->getRouteName(), $this->store->items->findAllForCollection($collection));
    }
}
