<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * Real generator with strict_requirements null (the production recipe), which checks nothing by itself.
 */
final class BreadcrumbUrlResolverRequirementsTest extends TestCase
{
    /**
     * @return iterable<string, array{Route, array<string, mixed>, string|null}>
     */
    public static function cases(): iterable
    {
        $number = new Route('/n/{id}', [], ['id' => '\d+']);
        yield 'matches' => [$number, ['id' => '42'], '/n/42'];
        yield 'integer value' => [$number, ['id' => 42], '/n/42'];
        yield 'breaks the requirement' => [$number, ['id' => 'abc'], null];
        yield 'partial match is not enough' => [$number, ['id' => '42abc'], null];
        yield 'trailing newline' => [$number, ['id' => "42\n"], null];
        yield 'null value' => [$number, ['id' => null], null];
        yield 'missing parameter' => [$number, [], null];

        $optional = new Route('/n/{id}', ['id' => '1'], ['id' => '\d+']);
        yield 'optional, left out' => [$optional, [], '/n'];
        yield 'optional, same as the default' => [$optional, ['id' => 1], '/n'];
        yield 'optional, other value checked' => [$optional, ['id' => 'abc'], null];

        $middle = new Route('/{a}/x/{b}', ['b' => '1'], ['a' => '\d+', 'b' => '\d+']);
        yield 'variable before static text is checked' => [$middle, ['a' => 'abc'], null];
        yield 'static text after an optional variable' => [$middle, ['a' => '5'], '/5/x'];

        $defaultFirst = new Route('/{a}/{b}', ['a' => 'bad', 'b' => '1'], ['a' => '[a-z]+', 'b' => '\d+']);
        yield 'default kept before a given value' => [$defaultFirst, ['b' => '2'], '/bad/2'];

        yield 'look-around ignored' => [new Route('/l/{id}', [], ['id' => '(?!admin)\w+']), ['id' => 'team'], '/l/team'];
        yield 'look-around, value outside the rest' => [new Route('/l/{id}', [], ['id' => '(?!admin)\w+']), ['id' => 'a/b'], null];
        yield 'unicode requirement' => [new Route('/u/{s}', [], ['s' => '\p{L}+'], ['utf8' => true]), ['s' => 'añb'], '/u/a%C3%B1b'];
        yield 'default requirement stays open' => [new Route('/o/{s}'), ['s' => 'a b'], '/o/a%20b'];
        yield 'default requirement refuses a slash' => [new Route('/o/{s}'), ['s' => 'a/b'], null];
        yield 'query parameter is not a route variable' => [new Route('/o'), ['q' => 'a/b'], '/o?q=a/b'];

        $host = new Route('/t', [], [], [], '{sub}.example.test');
        yield 'host, valid' => [$host, ['sub' => 'team-1'], '//team-1.example.test/t'];
        yield 'host, integer' => [$host, ['sub' => 7], '//7.example.test/t'];
        yield 'host, unicode label' => [$host, ['sub' => 'españa'], '//españa.example.test/t'];
        yield 'host, fragment' => [$host, ['sub' => 'evil#'], null];
        yield 'host, dots break the default requirement' => [$host, ['sub' => 'evil.test#'], null];
        yield 'host, query' => [$host, ['sub' => 'evil?'], null];
        yield 'host, path' => [$host, ['sub' => 'evil/'], null];
        yield 'host, backslash' => [$host, ['sub' => 'evil\\'], null];
        yield 'host, userinfo' => [$host, ['sub' => 'evil@'], null];
        yield 'host, port' => [$host, ['sub' => 'evil:80'], null];
        yield 'host, encoded' => [$host, ['sub' => 'evil%23'], null];
        yield 'host, space' => [$host, ['sub' => 'evil '], null];
        yield 'host, not UTF-8' => [$host, ['sub' => "\xff"], null];
        yield 'host, missing' => [$host, [], null];

        $wide = new Route('/t', [], ['sub' => '.+'], [], '{sub}.example.test');
        yield 'host, a wide requirement does not open it' => [$wide, ['sub' => 'evil.test#'], null];
        yield 'host, a wide requirement allows dots' => [$wide, ['sub' => 'a.b'], '//a.b.example.test/t'];

        yield 'value that is not scalar' => [$number, ['id' => new stdClass()], null];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('cases')]
    public function testResolve(Route $route, array $params, ?string $expected): void
    {
        self::assertSame($expected, $this->resolver($route)->resolve('target', $params, null)[0]);
    }

    public function testValueCopiedFromTheCurrentRouteIsCheckedToo(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route_params', ['id' => 'abc']);

        self::assertNull($this->resolver(new Route('/n/{id}', [], ['id' => '\d+']), $request)->resolve('target', [], null)[0]);
    }

    public function testContextParameterIsChecked(): void
    {
        $context = new RequestContext();
        $context->setParameter('tenant', 'abc');
        $route = new Route('/n/{tenant}', [], ['tenant' => '\d+']);
        self::assertNull($this->resolver($route, null, $context)->resolve('target', [], null)[0]);

        $context->setParameter('tenant', '5');
        self::assertSame('/n/5', $this->resolver($route, null, $context)->resolve('target', [], null)[0]);
    }

    public function testLocalizedRouteUsesItsOwnLocaleNotTheContexts(): void
    {
        $context = new RequestContext();
        $context->setParameter('_locale', 'fr');
        $route = new Route('/{_locale}/x', ['_canonical_route' => 'target', '_locale' => 'es'], ['_locale' => 'es']);

        self::assertSame('/es/x', $this->resolver($route, null, $context)->resolve('target', [], null)[0]);
    }

    private function resolver(Route $route, ?Request $request = null, ?RequestContext $context = null): BreadcrumbUrlResolver
    {
        $routes = new RouteCollection();
        $routes->add('target', $route);
        $generator = new UrlGenerator($routes, $context ?? new RequestContext());
        $generator->setStrictRequirements(null);
        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routes);
        $stack = new RequestStack();
        if (null !== $request) {
            $stack->push($request);
        }

        return new BreadcrumbUrlResolver($generator, $stack, $router);
    }
}
