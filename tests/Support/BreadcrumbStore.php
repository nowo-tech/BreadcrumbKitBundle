<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Support;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\BreadcrumbKitBundle\Doctrine\AfterCommitMiddleware;
use Nowo\BreadcrumbKitBundle\Doctrine\BreadcrumbChangeOutbox;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCacheInvalidator;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel\Entity\HostNote;
use Nowo\BreadcrumbKitBundle\Transaction\BreadcrumbTransactionRunner;
use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Test double of a host: a real entity manager (SQLite in memory, or the server named by BREADCRUMB_TEST_DATABASE_URL)
 * with the bundle's middleware, invalidator, runner and importer wired as the container wires them. Not part of the
 * bundle.
 */
final readonly class BreadcrumbStore
{
    public EntityManager $entityManager;
    public BreadcrumbChangeOutbox $outbox;
    public BreadcrumbCacheInvalidator $invalidator;
    public BreadcrumbTransactionRunner $runner;
    public StoreManagerRegistry $registry;
    public BreadcrumbCollectionRepository $collections;
    public BreadcrumbItemRepository $items;

    /**
     * @param string|null           $url                DBAL URL; null: SQLite in memory
     * @param array<string, string> $schemaReplacements text replaced in the CREATE statements (a column collation, an index)
     */
    public function __construct(?string $url = null, ?CacheItemPoolInterface $pool = null, ?EventDispatcherInterface $dispatcher = null, bool $createSchema = true, private array $schemaReplacements = [])
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([\dirname(__DIR__, 2).'/src/Entity', \dirname(__DIR__).'/Integration/Kernel/Entity'], true);
        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }
        $this->outbox = new BreadcrumbChangeOutbox();
        $configuration->setMiddlewares([new AfterCommitMiddleware($this->outbox)]);
        $connection = null === $url
            ? DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration)
            : DriverManager::getConnection((new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($url), $configuration);
        $eventManager = new EventManager();
        $this->entityManager = new EntityManager($connection, $configuration, $eventManager);
        if ($createSchema) {
            $this->createSchema();
        }

        $this->registry = new StoreManagerRegistry($this->entityManager);
        $this->collections = new BreadcrumbCollectionRepository($this->registry);
        $this->items = new BreadcrumbItemRepository($this->registry);

        $this->invalidator = new BreadcrumbCacheInvalidator($pool, $dispatcher, $this->outbox);
        $eventManager->addEventListener([Events::onFlush, Events::postFlush], $this->invalidator);
        $this->runner = new BreadcrumbTransactionRunner($this->entityManager, null, null, 1, 0, $this->invalidator);
    }

    public function importer(?TranslatorInterface $translator = null): BreadcrumbImporter
    {
        return new BreadcrumbImporter($this->collections, $this->items, $this->entityManager, $translator ?? $this->translator(), changeNotifier: $this->invalidator, transactionRunner: $this->runner);
    }

    /** A translator that answers the message id followed by its parameters as JSON. */
    public function translator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            /**
             * @param array<string, mixed> $parameters
             */
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return $id.([] === $parameters ? '' : json_encode($parameters, \JSON_THROW_ON_ERROR));
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };
    }

    public function connection(): Connection
    {
        return $this->entityManager->getConnection();
    }

    public function createSchema(): void
    {
        $tool = new SchemaTool($this->entityManager);
        $classes = [
            $this->entityManager->getClassMetadata(BreadcrumbCollection::class),
            $this->entityManager->getClassMetadata(BreadcrumbItem::class),
            $this->entityManager->getClassMetadata(HostNote::class),
        ];
        $tool->dropSchema($classes);
        foreach ($tool->getCreateSchemaSql($classes) as $sql) {
            $this->entityManager->getConnection()->executeStatement(strtr($sql, $this->schemaReplacements));
        }
    }

    /** URL of the real server of the CI "Load readiness" job; null when the test runs on SQLite only. */
    public static function serverUrl(): ?string
    {
        $url = getenv('BREADCRUMB_TEST_DATABASE_URL');

        return \is_string($url) && '' !== $url ? $url : null;
    }
}
