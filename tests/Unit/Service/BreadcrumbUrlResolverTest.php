<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class BreadcrumbUrlResolverTest extends TestCase
{
    public function testResolveReturnsNullForEmptyRouteName(): void
    {
        $resolver = new BreadcrumbUrlResolver(
            $this->createMock(UrlGeneratorInterface::class),
            new RequestStack(),
            $this->createMock(RouterInterface::class),
        );

        [$url, $params] = $resolver->resolve('', ['id' => 1], null);

        self::assertNull($url);
        self::assertSame([], $params);
    }

    public function testResolveGeneratesUrlWithStaticParams(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('product_show', ['id' => 5], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/product/5');

        $route = new Route('/product/{id}');
        $collection = new RouteCollection();
        $collection->add('product_show', $route);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $request = Request::create('/product/5');
        $request->attributes->set('_route_params', ['id' => 5]);
        $stack = new RequestStack();
        $stack->push($request);

        $resolver = new BreadcrumbUrlResolver($urlGenerator, $stack, $router);
        [$url, $params] = $resolver->resolve('product_show', ['id' => 5], null);

        self::assertSame('/product/5', $url);
        self::assertSame(['id' => 5], $params);
    }

    public function testResolveMergesPathVariablesFromCurrentRequest(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('product_show', ['id' => 7, 'slug' => 'foo'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/product/7/foo');

        $route = new Route('/product/{id}/{slug}');
        $collection = new RouteCollection();
        $collection->add('product_show', $route);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $request = Request::create('/product/7/foo');
        $request->attributes->set('_route_params', ['id' => 7, 'slug' => 'foo']);
        $stack = new RequestStack();
        $stack->push($request);

        $resolver = new BreadcrumbUrlResolver($urlGenerator, $stack, $router);
        [$url, $params] = $resolver->resolve('product_show', [], null);

        self::assertSame('/product/7/foo', $url);
        self::assertSame(['id' => 7, 'slug' => 'foo'], $params);
    }

    public function testResolveMergesDynamicKeysFromCurrentRequest(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('catalog', ['section' => 'books'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/catalog/books');

        $route = new Route('/catalog/{section}');
        $collection = new RouteCollection();
        $collection->add('catalog', $route);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $request = Request::create('/catalog/books');
        $request->attributes->set('_route_params', ['section' => 'books']);
        $stack = new RequestStack();
        $stack->push($request);

        $resolver = new BreadcrumbUrlResolver($urlGenerator, $stack, $router);
        [$url, $params] = $resolver->resolve('catalog', [], ['section']);

        self::assertSame('/catalog/books', $url);
        self::assertSame(['section' => 'books'], $params);
    }

    public function testResolveInjectsLocaleWhenRouteRequiresIt(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('page', ['_locale' => 'es', 'id' => 1], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/es/page/1');

        $route = new Route('/{_locale}/page/{id}');
        $collection = new RouteCollection();
        $collection->add('page', $route);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $request = Request::create('/es/page/1');
        $request->setLocale('es');
        $request->attributes->set('_route_params', ['id' => 1]);
        $stack = new RequestStack();
        $stack->push($request);

        $resolver = new BreadcrumbUrlResolver($urlGenerator, $stack, $router);
        [$url, $params] = $resolver->resolve('page', ['id' => 1], null);

        self::assertSame('/es/page/1', $url);
        self::assertSame(['_locale' => 'es', 'id' => 1], $params);
    }

    public function testResolveReturnsNullWhenUrlGeneratorThrows(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willThrowException(new \RuntimeException('missing route'));

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        $resolver = new BreadcrumbUrlResolver($urlGenerator, new RequestStack(), $router);
        [$url, $params] = $resolver->resolve('missing', ['a' => 1], null);

        self::assertNull($url);
        self::assertSame(['a' => 1], $params);
    }

    public function testResolveSurvivesRouterException(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->willReturn('/ok');

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willThrowException(new \RuntimeException('broken router'));

        $resolver = new BreadcrumbUrlResolver($urlGenerator, new RequestStack(), $router);
        [$url, $params] = $resolver->resolve('any', [], null);

        self::assertSame('/ok', $url);
        self::assertSame([], $params);
    }

    public function testResolveWorksWithoutCurrentRequest(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('home', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/');

        $route = new Route('/');
        $collection = new RouteCollection();
        $collection->add('home', $route);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $resolver = new BreadcrumbUrlResolver($urlGenerator, new RequestStack(), $router);
        [$url, $params] = $resolver->resolve('home', [], ['id']);

        self::assertSame('/', $url);
        self::assertSame([], $params);
    }
}
