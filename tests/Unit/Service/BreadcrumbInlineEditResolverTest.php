<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbInlineEditAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbInlineEditResolver;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolverInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
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
        $locator->method('get')->willReturn(new \stdClass());

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
            requestStack: $this->requestWithEditParam('yes'),
            collectionRepository: $this->collectionRepo($collection),
            locator: $this->locatorWithChecker('admin', $checker),
            urlGenerator: $urlGenerator,
            tokenStorage: $tokenStorage,
        );

        self::assertTrue($resolver->resolve('default')->show);
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

        $ref = new \ReflectionProperty(BreadcrumbItem::class, 'id');
        $ref->setValue($item, $id);

        return $item;
    }

    private function requestWithEditParam(string $value = '1'): RequestStack
    {
        $request = Request::create('/?edit='.$value);
        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }

    private function collectionWithId(int $id): BreadcrumbCollection
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode('default');
        $collection->setContextKey('');

        $ref = new \ReflectionProperty(BreadcrumbCollection::class, 'id');
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
