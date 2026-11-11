<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbEditAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbInlineEditAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbEditContext;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbInlineEditResolver;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolverInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BreadcrumbInlineEditResolverTest extends TestCase
{
    public function testResolveReturnsEmptyWhenDashboardDisabled(): void
    {
        $resolver = $this->makeResolver(dashboardEnabled: false, queryParamName: 'edit');

        self::assertFalse($resolver->resolve('default')->show);
    }

    public function testResolveReturnsEmptyWhenQueryParamNameMissing(): void
    {
        $resolver = $this->makeResolver(dashboardEnabled: true, queryParamName: null);

        self::assertFalse($resolver->resolve('default')->show);
    }

    public function testResolveReturnsEmptyWhenNoRequest(): void
    {
        $resolver = $this->makeResolver(dashboardEnabled: true, queryParamName: 'edit');

        self::assertFalse($resolver->resolve('default')->show);
    }

    public function testResolveReturnsEmptyWhenQueryParamNotTruthy(): void
    {
        $request = Request::create('/?edit=0');
        $stack = new RequestStack();
        $stack->push($request);

        $resolver = $this->makeResolver(
            dashboardEnabled: true,
            queryParamName: 'edit',
            requestStack: $stack,
        );

        self::assertFalse($resolver->resolve('default')->show);
    }

    public function testResolveReturnsEmptyWhenCollectionNotFound(): void
    {
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn(null);

        $resolver = $this->makeResolver(
            dashboardEnabled: true,
            queryParamName: 'edit',
            requestStack: $this->requestWithEditParam(),
            collectionRepository: $colRepo,
        );

        self::assertFalse($resolver->resolve('missing')->show);
    }

    public function testResolveReturnsEmptyWhenAccessKeyMissing(): void
    {
        $collection = $this->collectionWithId(1);
        $collection->setInlineEditAccessKey(null);

        $resolver = $this->makeResolver(
            dashboardEnabled: true,
            queryParamName: 'edit',
            requestStack: $this->requestWithEditParam(),
            collectionRepository: $this->collectionRepo($collection),
        );

        self::assertFalse($resolver->resolve('default')->show);
    }

    public function testResolveReturnsEmptyWhenLocatorHasNoChecker(): void
    {
        $collection = $this->collectionWithId(1);
        $collection->setInlineEditAccessKey('admin');

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->with('admin')->willReturn(false);

        $resolver = $this->makeResolver(
            dashboardEnabled: true,
            queryParamName: 'edit',
            requestStack: $this->requestWithEditParam(),
            collectionRepository: $this->collectionRepo($collection),
            locator: $locator,
        );

        self::assertFalse($resolver->resolve('default')->show);
    }

    public function testResolveReturnsEmptyWhenCheckerDeniesAccess(): void
    {
        $collection = $this->collectionWithId(1);
        $collection->setInlineEditAccessKey('admin');

        $checker = $this->createMock(BreadcrumbInlineEditAccessCheckerInterface::class);
        $checker->method('canUseInlineBreadcrumbEditor')->willReturn(false);

        $resolver = $this->makeResolver(
            dashboardEnabled: true,
            queryParamName: 'edit',
            requestStack: $this->requestWithEditParam(),
            collectionRepository: $this->collectionRepo($collection),
            locator: $this->locatorWithChecker('admin', $checker),
        );

        self::assertFalse($resolver->resolve('default')->show);
    }

    public function testResolveHappyPathForExistingItem(): void
    {
        $collection = $this->collectionWithId(5);
        $collection->setInlineEditAccessKey('admin');
        $item = $this->itemWithId(99, $collection, null, 'page', 'Page');
        $item->setLinkEnabled(false);

        $colRepo = $this->collectionRepo($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$item]);
        $itemRepo->method('find')->with(99)->willReturn($item);

        $request = Request::create('/?edit=1');
        $request->attributes->set('_route', 'page');
        $request->attributes->set('_route_params', []);
        $stack = new RequestStack();
        $stack->push($request);

        $loader = new BreadcrumbLoader(
            $colRepo,
            $itemRepo,
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
        );

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('nowo_breadcrumb_kit_dashboard_items_edit', ['collectionId' => 5, 'id' => 99])
            ->willReturn('/admin/items/99/edit');

        $checker = $this->createMock(BreadcrumbInlineEditAccessCheckerInterface::class);
        $checker->method('canUseInlineBreadcrumbEditor')->willReturn(true);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Edit breadcrumb');

        $resolver = $this->makeResolver(
            dashboardEnabled: true,
            queryParamName: 'edit',
            requestStack: $stack,
            collectionRepository: $colRepo,
            loader: $loader,
            locator: $this->locatorWithChecker('admin', $checker),
            urlGenerator: $urlGenerator,
            translator: $translator,
        );

        $ctx = $resolver->resolve('default');

        self::assertTrue($ctx->show);
        self::assertSame('/admin/items/99/edit', $ctx->iframeUrl);
        self::assertSame('Edit breadcrumb', $ctx->modalTitle);
    }

    public function testResolveHappyPathForNewItemWhenNoMatch(): void
    {
        $collection = $this->collectionWithId(5);
        $collection->setInlineEditAccessKey('admin');

        $colRepo = $this->collectionRepo($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([]);

        $request = Request::create('/?edit=1');
        $request->attributes->set('_route', 'unmatched');
        $request->attributes->set('_route_params', []);
        $stack = new RequestStack();
        $stack->push($request);

        $loader = new BreadcrumbLoader(
            $colRepo,
            $itemRepo,
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
        );

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('nowo_breadcrumb_kit_dashboard_items_new', ['collectionId' => 5])
            ->willReturn('/admin/items/new');

        $checker = $this->createMock(BreadcrumbInlineEditAccessCheckerInterface::class);
        $checker->method('canUseInlineBreadcrumbEditor')->willReturn(true);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('New breadcrumb');

        $resolver = $this->makeResolver(
            dashboardEnabled: true,
            queryParamName: 'edit',
            requestStack: $stack,
            collectionRepository: $colRepo,
            loader: $loader,
            locator: $this->locatorWithChecker('admin', $checker),
            urlGenerator: $urlGenerator,
            translator: $translator,
        );

        $ctx = $resolver->resolve('default');

        self::assertTrue($ctx->show);
        self::assertSame('/admin/items/new', $ctx->iframeUrl);
        self::assertSame('New breadcrumb', $ctx->modalTitle);
    }

    public function testResolveReturnsEmptyWhenUrlGenerationFails(): void
    {
        $collection = $this->collectionWithId(5);
        $collection->setInlineEditAccessKey('admin');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willThrowException(new RouteNotFoundException());

        $checker = $this->createMock(BreadcrumbInlineEditAccessCheckerInterface::class);
        $checker->method('canUseInlineBreadcrumbEditor')->willReturn(true);

        $resolver = $this->makeResolver(
            dashboardEnabled: true,
            queryParamName: 'edit',
            requestStack: $this->requestWithEditParam(),
            collectionRepository: $this->collectionRepo($collection),
            locator: $this->locatorWithChecker('admin', $checker),
            urlGenerator: $urlGenerator,
        );

        self::assertFalse($resolver->resolve('default')->show);
    }

    public function testResolveReturnsEmptyWhenCheckerIsNotInterface(): void
    {
        $collection = $this->collectionWithId(1);
        $collection->setInlineEditAccessKey('admin');

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn(new stdClass());

        $resolver = $this->makeResolver(
            dashboardEnabled: true,
            queryParamName: 'edit',
            requestStack: $this->requestWithEditParam(),
            collectionRepository: $this->collectionRepo($collection),
            locator: $locator,
        );

        self::assertFalse($resolver->resolve('default')->show);
    }

    public function testResolveUsesAuthenticatedUserFromTokenStorage(): void
    {
        $collection = $this->collectionWithId(3);
        $collection->setInlineEditAccessKey('admin');

        $user = $this->createMock(UserInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $checker = $this->createMock(BreadcrumbInlineEditAccessCheckerInterface::class);
        $checker->expects(self::once())
            ->method('canUseInlineBreadcrumbEditor')
            ->with(self::isInstanceOf(Request::class), $user)
            ->willReturn(true);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/new');

        $resolver = $this->makeResolver(
            dashboardEnabled: true,
            queryParamName: 'edit',
            requestStack: $this->requestWithEditParam('yes', firewall: true),
            collectionRepository: $this->collectionRepo($collection),
            locator: $this->locatorWithChecker('admin', $checker),
            urlGenerator: $urlGenerator,
            tokenStorage: $tokenStorage,
        );

        self::assertTrue($resolver->resolve('default')->show);
    }

    public function testStaleTokenIsNotPassedToCheckerWhenNoFirewallRanForTheRequest(): void
    {
        $collection = $this->collectionWithId(3);
        $collection->setInlineEditAccessKey('admin');

        $user = $this->createMock(UserInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $seenUsers = [];
        $checker = $this->createMock(BreadcrumbInlineEditAccessCheckerInterface::class);
        $checker->method('canUseInlineBreadcrumbEditor')
            ->willReturnCallback(static function (Request $request, ?UserInterface $user) use (&$seenUsers): bool {
                $seenUsers[] = $user;

                return null !== $user;
            });

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/new');

        foreach ([true, false] as $firewall) {
            $resolver = $this->makeResolver(
                dashboardEnabled: true,
                queryParamName: 'edit',
                requestStack: $this->requestWithEditParam('yes', firewall: $firewall),
                collectionRepository: $this->collectionRepo($collection),
                locator: $this->locatorWithChecker('admin', $checker),
                urlGenerator: $urlGenerator,
                tokenStorage: $tokenStorage,
            );

            self::assertSame($firewall, $resolver->resolve('default')->show);
        }

        self::assertSame([$user, null], $seenUsers);
    }

    public function testContextCheckerDecidesWithTheFrameworkFreeContext(): void
    {
        $collection = $this->collectionWithId(3);
        $collection->setInlineEditAccessKey('editors');

        $checker = new class implements BreadcrumbEditAccessCheckerInterface {
            /** @var list<BreadcrumbEditContext> */
            public array $seen = [];

            public function canEditInline(BreadcrumbEditContext $context): bool
            {
                $this->seen[] = $context;

                return $context->hasRole('ROLE_EDITOR');
            }
        };

        $stack = $this->requestWithEditParam('1', firewall: true);
        $request = $stack->getCurrentRequest();
        self::assertInstanceOf(Request::class, $request);
        $request->attributes->set('_route', 'project_show');
        $request->attributes->set('_route_params', ['id' => '7', 'object' => new stdClass()]);
        $request->setLocale('es');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/new');

        $resolver = $this->makeResolver(
            dashboardEnabled: true,
            queryParamName: 'edit',
            requestStack: $stack,
            collectionRepository: $this->collectionRepo($collection),
            locator: $this->locatorWithChecker('editors', $checker),
            urlGenerator: $urlGenerator,
            tokenStorage: $this->tokenStorageFor(new InMemoryUser('alice', null, ['ROLE_EDITOR'])),
        );

        self::assertTrue($resolver->resolve('default', 'ctx')->show);
        $seen = $checker->seen;
        self::assertCount(1, $seen);
        self::assertSame('project_show', $seen[0]->routeName);
        self::assertSame(['id' => '7'], $seen[0]->routeParameters);
        self::assertSame('es', $seen[0]->locale);
        self::assertSame('/', $seen[0]->path);
        self::assertSame('default', $seen[0]->collectionCode);
        self::assertSame('ctx', $seen[0]->contextKey);
        self::assertSame('alice', $seen[0]->userIdentifier);
        self::assertSame(['ROLE_EDITOR'], $seen[0]->roles);
        self::assertTrue($seen[0]->isAuthenticated());
    }

    public function testContextCheckerDenyHidesTheEditor(): void
    {
        $collection = $this->collectionWithId(3);
        $collection->setInlineEditAccessKey('editors');
        $checker = $this->createMock(BreadcrumbEditAccessCheckerInterface::class);
        $checker->expects(self::once())->method('canEditInline')->willReturn(false);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $resolver = $this->makeResolver(
            dashboardEnabled: true,
            queryParamName: 'edit',
            requestStack: $this->requestWithEditParam('1', firewall: true),
            collectionRepository: $this->collectionRepo($collection),
            locator: $this->locatorWithChecker('editors', $checker),
            urlGenerator: $urlGenerator,
            tokenStorage: $this->tokenStorageFor(new InMemoryUser('alice', null, ['ROLE_EDITOR'])),
        );

        $context = $resolver->resolve('default');
        self::assertFalse($context->show);
        self::assertNull($context->iframeUrl);
    }

    public function testServiceImplementingBothPortsIsAskedThroughTheNewOneOnly(): void
    {
        $collection = $this->collectionWithId(3);
        $collection->setInlineEditAccessKey('editors');
        $checker = new class implements BreadcrumbEditAccessCheckerInterface, BreadcrumbInlineEditAccessCheckerInterface {
            public int $legacyCalls = 0;

            public function canEditInline(BreadcrumbEditContext $context): bool
            {
                return false;
            }

            public function canUseInlineBreadcrumbEditor(Request $request, ?UserInterface $user): bool
            {
                ++$this->legacyCalls;

                return true;
            }
        };

        $resolver = $this->makeResolver(
            dashboardEnabled: true,
            queryParamName: 'edit',
            requestStack: $this->requestWithEditParam('1', firewall: true),
            collectionRepository: $this->collectionRepo($collection),
            locator: $this->locatorWithChecker('editors', $checker),
            tokenStorage: $this->tokenStorageFor(new InMemoryUser('alice', null, ['ROLE_EDITOR'])),
        );

        self::assertFalse($resolver->resolve('default')->show, 'the new port denies; the legacy answer is never used');
        self::assertSame(0, $checker->legacyCalls);
    }

    public function testLegacyCheckerTriggersADeprecation(): void
    {
        $collection = $this->collectionWithId(3);
        $collection->setInlineEditAccessKey('admin');
        $checker = $this->createMock(BreadcrumbInlineEditAccessCheckerInterface::class);
        $checker->method('canUseInlineBreadcrumbEditor')->willReturn(false);

        $resolver = $this->makeResolver(
            dashboardEnabled: true,
            queryParamName: 'edit',
            requestStack: $this->requestWithEditParam(),
            collectionRepository: $this->collectionRepo($collection),
            locator: $this->locatorWithChecker('admin', $checker),
        );

        $messages = [];
        // Test-only capture, restored in finally below.
        set_error_handler(static function (int $level, string $message) use (&$messages): bool { // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler
            $messages[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            self::assertFalse($resolver->resolve('default')->show);
        } finally {
            restore_error_handler();
        }

        self::assertCount(1, $messages);
        self::assertStringContainsString('BreadcrumbEditAccessCheckerInterface', $messages[0]);
        self::assertStringContainsString('removed in 3.0', $messages[0]);
    }

    /**
     * Same request, same token, same policy written against each port: the resolver must give the same answer
     * (no wider access through the new port) and hand both the same identity; anonymous cases stay anonymous.
     *
     * @return iterable<string, array{?UserInterface, bool, bool, string}>
     */
    public static function accessScenarios(): iterable
    {
        $editor = new InMemoryUser('alice', null, ['ROLE_EDITOR', 'ROLE_USER']);
        $reader = new InMemoryUser('bob', null, ['ROLE_USER']);

        yield 'no security component' => [null, false, false, 'project_show'];
        yield 'firewall, no token' => [null, true, true, 'project_show'];
        yield 'editor without firewall (stale worker token)' => [$editor, false, true, 'project_show'];
        yield 'editor behind firewall' => [$editor, true, true, 'project_show'];
        yield 'editor on a blocked route' => [$editor, true, true, 'secret_page'];
        yield 'reader behind firewall' => [$reader, true, true, 'project_show'];
        yield 'reader without firewall' => [$reader, false, true, 'project_show'];
    }

    #[DataProvider('accessScenarios')]
    public function testLegacyAndContextPortsGiveTheSameDecision(?UserInterface $user, bool $firewall, bool $withTokenStorage, string $route): void
    {
        $policies = [
            'role' => [
                static fn (Request $r, ?UserInterface $u): bool => null !== $u && \in_array('ROLE_EDITOR', $u->getRoles(), true) && 'secret_page' !== $r->attributes->get('_route'),
                static fn (BreadcrumbEditContext $c): bool => $c->hasRole('ROLE_EDITOR') && 'secret_page' !== $c->routeName,
            ],
            'authenticated' => [
                static fn (Request $r, ?UserInterface $u): bool => null !== $u,
                static fn (BreadcrumbEditContext $c): bool => $c->isAuthenticated(),
            ],
            'identifier' => [
                static fn (Request $r, ?UserInterface $u): bool => 'alice' === $u?->getUserIdentifier(),
                static fn (BreadcrumbEditContext $c): bool => 'alice' === $c->userIdentifier,
            ],
        ];

        foreach ($policies as $name => [$legacyPolicy, $contextPolicy]) {
            $legacySeen = [];
            $legacy = $this->createMock(BreadcrumbInlineEditAccessCheckerInterface::class);
            $legacy->method('canUseInlineBreadcrumbEditor')->willReturnCallback(
                static function (Request $r, ?UserInterface $u) use ($legacyPolicy, &$legacySeen): bool {
                    $legacySeen = [$u?->getUserIdentifier(), null === $u ? [] : array_values($u->getRoles())];

                    return $legacyPolicy($r, $u);
                },
            );
            $contextSeen = [];
            $modern = $this->createMock(BreadcrumbEditAccessCheckerInterface::class);
            $modern->method('canEditInline')->willReturnCallback(
                static function (BreadcrumbEditContext $c) use ($contextPolicy, &$contextSeen): bool {
                    $contextSeen = [$c->userIdentifier, $c->roles];

                    return $contextPolicy($c);
                },
            );

            $decisions = [];
            foreach (['legacy' => $legacy, 'context' => $modern] as $port => $checker) {
                $stack = $this->requestWithEditParam('1', firewall: $firewall);
                $request = $stack->getCurrentRequest();
                self::assertInstanceOf(Request::class, $request);
                $request->attributes->set('_route', $route);
                $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
                $urlGenerator->method('generate')->willReturn('/new');
                $resolver = $this->makeResolver(
                    dashboardEnabled: true,
                    queryParamName: 'edit',
                    requestStack: $stack,
                    collectionRepository: $this->collectionRepo($this->collectionWithAccessKey()),
                    locator: $this->locatorWithChecker('admin', $checker),
                    urlGenerator: $urlGenerator,
                    tokenStorage: $withTokenStorage ? $this->tokenStorageFor($user) : null,
                );
                $decisions[$port] = $resolver->resolve('default')->show;
            }

            self::assertSame($decisions['legacy'], $decisions['context'], $name.': same decision');
            self::assertSame($legacySeen, $contextSeen, $name.': same identity');
            if (!$firewall) {
                self::assertSame([null, []], $contextSeen, $name.': no firewall means anonymous');
            }
        }
    }

    private function collectionWithAccessKey(): BreadcrumbCollection
    {
        $collection = $this->collectionWithId(3);
        $collection->setInlineEditAccessKey('admin');

        return $collection;
    }

    private function tokenStorageFor(?UserInterface $user): TokenStorage
    {
        $storage = new TokenStorage();
        if (null !== $user) {
            $storage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        }

        return $storage;
    }

    private function makeResolver(
        bool $dashboardEnabled,
        ?string $queryParamName,
        ?RequestStack $requestStack = null,
        ?BreadcrumbCollectionRepository $collectionRepository = null,
        ?BreadcrumbLoader $loader = null,
        ?ContainerInterface $locator = null,
        ?UrlGeneratorInterface $urlGenerator = null,
        ?TranslatorInterface $translator = null,
        ?TokenStorageInterface $tokenStorage = null,
    ): BreadcrumbInlineEditResolver {
        return new BreadcrumbInlineEditResolver(
            $requestStack ?? new RequestStack(),
            $collectionRepository ?? $this->createMock(BreadcrumbCollectionRepository::class),
            $loader ?? $this->minimalLoader(),
            $locator ?? $this->createMock(ContainerInterface::class),
            $urlGenerator ?? $this->createMock(UrlGeneratorInterface::class),
            $translator ?? $this->createMock(TranslatorInterface::class),
            $tokenStorage,
            $queryParamName,
            $dashboardEnabled,
        );
    }

    private function minimalLoader(): BreadcrumbLoader
    {
        return new BreadcrumbLoader(
            $this->createMock(BreadcrumbCollectionRepository::class),
            $this->createMock(BreadcrumbItemRepository::class),
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            new RequestStack(),
            'en',
        );
    }

    private function itemWithId(
        int $id,
        BreadcrumbCollection $collection,
        ?BreadcrumbItem $parent,
        string $route,
        ?string $label,
    ): BreadcrumbItem {
        $item = new BreadcrumbItem();
        $item->setCollection($collection);
        $item->setParent($parent);
        $item->setRouteName($route);
        $item->setLabel($label);

        $ref = new ReflectionProperty(BreadcrumbItem::class, 'id');
        $ref->setValue($item, $id);

        return $item;
    }

    private function requestWithEditParam(string $value = '1', bool $firewall = false): RequestStack
    {
        $request = Request::create('/?edit='.$value);
        if ($firewall) {
            $request->attributes->set('_firewall_context', 'security.firewall.map.context.main');
        }
        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }

    private function collectionWithId(int $id): BreadcrumbCollection
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode('default');
        $collection->setContextKey('');

        $ref = new ReflectionProperty(BreadcrumbCollection::class, 'id');
        $ref->setValue($collection, $id);

        return $collection;
    }

    private function collectionRepo(BreadcrumbCollection $collection): BreadcrumbCollectionRepository
    {
        $repo = $this->createMock(BreadcrumbCollectionRepository::class);
        $repo->method('findOneByCodeAndContextKey')->willReturn($collection);

        return $repo;
    }

    private function locatorWithChecker(string $key, object $checker): ContainerInterface
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->with($key)->willReturn(true);
        $locator->method('get')->with($key)->willReturn($checker);

        return $locator;
    }
}
