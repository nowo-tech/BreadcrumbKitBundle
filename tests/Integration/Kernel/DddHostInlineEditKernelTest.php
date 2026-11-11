<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbInlineEditResolver;
use Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel\Entity\HostNote;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * REQ-ARCH-001 DDD host path for the inline editor (2.18): dedicated manager, host entities on the host manager, the
 * policy implements only the framework-free port. Also a kept-alive kernel loop (REQ-RUNTIME-002): no services
 * resetter, two users alternating, each gets its own decision, memory stays flat.
 */
final class DddHostInlineEditKernelTest extends TestCase
{
    private ?DddHostTestKernel $kernel = null;

    protected function setUp(): void
    {
        $dir = DddHostTestKernel::baseDir();
        (new Filesystem())->mkdir($dir);
        $this->kernel = new DddHostTestKernel($dir.'/host.sqlite', $dir.'/bundle.sqlite');
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        (new Filesystem())->remove(DddHostTestKernel::baseDir());
    }

    public function testOnlyTheNewPortDecidesAndAnonymousIsDenied(): void
    {
        [$host, $bundle] = $this->managersWithSchema();
        $note = new HostNote('pending host change');
        $host->persist($note);
        $this->importCollection();

        self::assertTrue($host->getMetadataFactory()->isTransient(BreadcrumbCollection::class));
        self::assertSame($bundle, $this->registry()->getManagerForClass(BreadcrumbCollection::class));

        $policy = $this->container()->get('app.breadcrumb_edit_policy');
        self::assertInstanceOf(EditorRolePolicy::class, $policy);

        self::assertFalse($this->resolveAs(null), 'anonymous is denied');
        self::assertNotNull($policy->lastContext);
        self::assertFalse($policy->lastContext->isAuthenticated());
        self::assertSame([], $policy->lastContext->roles);

        self::assertFalse($this->resolveAs(new InMemoryUser('bob', null, ['ROLE_USER'])));
        self::assertSame('bob', $policy->lastContext->userIdentifier);

        $resolver = $this->resolver();
        $this->enter(new InMemoryUser('alice', null, ['ROLE_EDITOR']), firewall: true);
        $context = $resolver->resolve('main');
        $this->leave();
        self::assertTrue($context->show);
        self::assertIsString($context->iframeUrl);
        self::assertStringStartsWith('/admin/breadcrumbs/', $context->iframeUrl);
        self::assertSame('alice', $policy->lastContext->userIdentifier);
        self::assertSame('project_show', $policy->lastContext->routeName);
        self::assertSame(['id' => '42'], $policy->lastContext->routeParameters);
        self::assertSame('main', $policy->lastContext->collectionCode);

        self::assertFalse($this->resolveAs(new InMemoryUser('alice', null, ['ROLE_EDITOR']), firewall: false), 'a token left by an earlier request without a firewall is ignored');

        self::assertSame(0, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM host_note'), 'the host manager is never flushed');
        self::assertTrue($host->contains($note));
    }

    public function testKeptAliveKernelGivesEachUserItsOwnDecision(): void
    {
        $this->managersWithSchema();
        $this->importCollection();
        $alice = new InMemoryUser('alice', null, ['ROLE_EDITOR']);
        $bob = new InMemoryUser('bob', null, ['ROLE_USER']);

        $baseline = 0;
        for ($i = 0; $i < 600; ++$i) {
            $user = 0 === $i % 3 ? null : (1 === $i % 3 ? $alice : $bob);
            self::assertSame($user === $alice, $this->resolveAs($user), 'iteration '.$i);
            if (30 === $i) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }
        gc_collect_cycles();
        self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $baseline, 'memory stays flat');
    }

    private function resolveAs(?InMemoryUser $user, bool $firewall = true): bool
    {
        $this->enter($user, $firewall);
        try {
            return $this->resolver()->resolve('main')->show;
        } finally {
            $this->leave();
        }
    }

    private function enter(?InMemoryUser $user, bool $firewall): void
    {
        $request = Request::create('/projects/42?edit=1');
        $request->attributes->set('_route', 'project_show');
        $request->attributes->set('_route_params', ['id' => '42']);
        if ($firewall) {
            $request->attributes->set('_firewall_context', 'security.firewall.map.context.main');
        }
        $this->requestStack()->push($request);
        // Like a kept-alive worker: the previous token stays unless this request's firewall replaces it.
        if (null !== $user || $firewall) {
            $this->tokenStorage()->setToken(null === $user ? null : new UsernamePasswordToken($user, 'main', $user->getRoles()));
        }
    }

    private function leave(): void
    {
        $this->requestStack()->pop();
    }

    private function importCollection(): void
    {
        $importer = $this->container()->get(BreadcrumbImporter::class);
        self::assertInstanceOf(BreadcrumbImporter::class, $importer);
        $importer->import([
            'collection' => ['code' => 'main', 'inlineEditAccessKey' => 'editors'],
            'items' => [['routeName' => 'project_show', 'label' => 'Project']],
        ]);
    }

    /**
     * @return array{EntityManagerInterface, EntityManagerInterface}
     */
    private function managersWithSchema(): array
    {
        $host = $this->registry()->getManager('default');
        $bundle = $this->registry()->getManager('breadcrumb');
        self::assertInstanceOf(EntityManagerInterface::class, $host);
        self::assertInstanceOf(EntityManagerInterface::class, $bundle);
        (new SchemaTool($host))->createSchema($host->getMetadataFactory()->getAllMetadata());
        (new SchemaTool($bundle))->createSchema($bundle->getMetadataFactory()->getAllMetadata());

        return [$host, $bundle];
    }

    private function resolver(): BreadcrumbInlineEditResolver
    {
        $resolver = $this->container()->get(BreadcrumbInlineEditResolver::class);
        self::assertInstanceOf(BreadcrumbInlineEditResolver::class, $resolver);

        return $resolver;
    }

    private function requestStack(): RequestStack
    {
        $stack = $this->container()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);

        return $stack;
    }

    private function tokenStorage(): TokenStorage
    {
        $storage = $this->container()->get('security.token_storage');
        self::assertInstanceOf(TokenStorage::class, $storage);

        return $storage;
    }

    private function registry(): ManagerRegistry
    {
        $registry = $this->container()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);

        return $registry;
    }

    private function container(): ContainerInterface
    {
        self::assertInstanceOf(DddHostTestKernel::class, $this->kernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }
}
