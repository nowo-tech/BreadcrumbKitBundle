<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Database;

use Nowo\BreadcrumbKitBundle\Command\GenerateBreadcrumbKitMigrationCommand;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Tests\Support\BreadcrumbStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `generate-migration --update` must create the columns under the names the ORM queries: with the default (camelCase)
 * naming strategy they are unquoted, so PostgreSQL folds them like the SchemaTool-created ones. Skipped without
 * BREADCRUMB_TEST_DATABASE_URL (the CI "Load readiness" job runs it with fail-on-skipped).
 */
final class MigrationUpdateAlterTest extends TestCase
{
    public function testTheGeneratedAlterCreatesColumnsTheOrmCanRead(): void
    {
        $url = BreadcrumbStore::serverUrl();
        if (null === $url) {
            self::markTestSkipped('Set BREADCRUMB_TEST_DATABASE_URL to run the real-server tests.');
        }
        $store = new BreadcrumbStore($url);
        $connection = $store->connection();
        // An installation from before the two columns existed.
        $connection->executeStatement('ALTER TABLE dashboard_breadcrumb_item DROP COLUMN pathPattern');
        $connection->executeStatement('ALTER TABLE dashboard_breadcrumb_item DROP COLUMN matchAttributes');

        $tester = new CommandTester(new GenerateBreadcrumbKitMigrationCommand($store->entityManager, 'default', 'dashboard_'));
        self::assertSame(0, $tester->execute(['--update' => true, '--dump' => true]));
        $statements = array_values(array_filter(array_map(static fn (string $line): string => rtrim(trim($line), ';'), explode("\n", $tester->getDisplay())), static fn (string $line): bool => str_starts_with($line, 'ALTER TABLE')));
        self::assertCount(2, $statements);
        foreach ($statements as $statement) {
            self::assertStringNotContainsString('"pathPattern"', $statement, 'columns of an unquoted mapping stay unquoted');
            self::assertStringNotContainsString('"matchAttributes"', $statement, 'columns of an unquoted mapping stay unquoted');
            $connection->executeStatement($statement);
        }

        $store->importer()->import(['collection' => ['code' => 'main'], 'items' => [['routeName' => '*', 'pathPattern' => '^/a', 'matchAttributes' => ['x' => 'y']]]]);
        $store->entityManager->clear();
        $items = $store->entityManager->createQuery('SELECT i FROM '.BreadcrumbItem::class.' i')->getResult();

        self::assertCount(1, $items);
        self::assertSame('^/a', $items[0]->getPathPattern());
        self::assertSame(['x' => 'y'], $items[0]->getMatchAttributes());
    }
}
