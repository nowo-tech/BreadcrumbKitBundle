<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\Generator\Dumper\CompiledUrlGeneratorDumper;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use TypeError;

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

    /**
     * @return iterable<string, array{array<string, mixed>, string|null, string|null, string|null}>
     */
    public static function localizedBaseName(): iterable
    {
        yield 'context locale, valid' => [['id' => '5'], 'en', null, '/en/n/5'];
        yield 'context locale, breaks the requirement' => [['id' => 'abc'], 'en', null, null];
        yield 'other context locale, its own requirement' => [['id' => 'abc'], 'es', null, '/es/n/abc'];
        yield 'regional context locale is cut' => [['id' => 'abc'], 'en_GB', null, null];
        yield 'regional context locale is cut, valid' => [['id' => '5'], 'en_GB', null, '/en/n/5'];
        yield 'parameter wins over the context' => [['id' => 'abc', '_locale' => 'en'], 'es', null, null];
        yield 'parameter wins over the context, valid' => [['id' => 'abc', '_locale' => 'es'], 'en', null, '/es/n/abc'];
        yield 'default locale when no other' => [['id' => 'abc'], null, 'en', null];
        yield 'default locale when no other, valid' => [['id' => '5'], null, 'en', '/en/n/5'];
        yield 'no locale at all: no such route' => [['id' => '5'], null, null, null];
        yield 'locale with no copy: no such route' => [['id' => '5'], 'fr', null, null];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('localizedBaseName')]
    public function testLocalizedRouteNamedByItsBaseName(array $params, ?string $contextLocale, ?string $defaultLocale, ?string $expected): void
    {
        $routes = new RouteCollection();
        $routes->add('loc.en', new Route('/en/n/{id}', ['_canonical_route' => 'loc', '_locale' => 'en'], ['id' => '\d+', '_locale' => 'en']));
        $routes->add('loc.es', new Route('/es/n/{id}', ['_canonical_route' => 'loc', '_locale' => 'es'], ['_locale' => 'es']));

        self::assertSame($expected, $this->resolverFor($routes, $contextLocale, $defaultLocale)->resolve('loc', $params, null)[0]);
    }

    public function testLocalizedRouteWithAHostVariableNamedByItsBaseName(): void
    {
        $routes = new RouteCollection();
        $routes->add('loc.en', new Route('/en/h', ['_canonical_route' => 'loc', '_locale' => 'en'], ['_locale' => 'en'], [], '{sub}.example.test'));

        $resolver = $this->resolverFor($routes, 'en', null);

        self::assertNull($resolver->resolve('loc', ['sub' => 'evil.test#'], null)[0]);
        self::assertNull($resolver->resolve('loc', ['sub' => 'evil#'], null)[0]);
        self::assertSame('//acme.example.test/en/h', $resolver->resolve('loc', ['sub' => 'acme'], null)[0]);
    }

    public function testFullNameOfALocalizedRouteIsChecked(): void
    {
        $routes = new RouteCollection();
        $routes->add('loc.en', new Route('/en/n/{id}', ['_canonical_route' => 'loc', '_locale' => 'en'], ['id' => '\d+', '_locale' => 'en']));

        self::assertNull($this->resolverFor($routes, 'es', null)->resolve('loc.en', ['id' => 'abc'], null)[0]);
        self::assertSame('/en/n/5', $this->resolverFor($routes, 'es', null)->resolve('loc.en', ['id' => '5'], null)[0]);
    }

    public function testRouteNamedLikeACopyButNotCanonicalIsNotTheTarget(): void
    {
        $routes = new RouteCollection();
        $routes->add('plain', new Route('/p/{id}', [], ['id' => '\d+']));
        $routes->add('plain.en', new Route('/other/{id}'));

        self::assertNull($this->resolverFor($routes, 'en', null)->resolve('plain', ['id' => 'abc'], null)[0]);
    }

    public function testAliasOfALocaleCopyIsFollowed(): void
    {
        $routes = new RouteCollection();
        $routes->add('real', new Route('/r/{id}', [], ['id' => '\d+']));
        $routes->addAlias('al.en', 'real');

        self::assertNull($this->resolverFor($routes, 'en', null)->resolve('al', ['id' => 'abc'], null)[0]);
        self::assertSame('/r/5', $this->resolverFor($routes, 'en', null)->resolve('al', ['id' => '5'], null)[0]);
    }

    /**
     * HD-I15: the route under `x.en` and the route `x` disagree, and which one is generated depends on the generator.
     *
     * @return iterable<string, array{RouteCollection, string}>
     */
    public static function namesTheGeneratorsReadDifferently(): iterable
    {
        $host = new Route('/al', [], [], [], '{sub}.example.test');

        $routes = new RouteCollection();
        $routes->add('real', new Route('/real/{sub}'));
        $routes->add('al', $host);
        $routes->addAlias('al.en', 'real');
        yield 'alias x.en beside a route x with a host variable' => [$routes, 'al'];

        $routes = new RouteCollection();
        $routes->add('nc', new Route('/nc/{sub}'));
        $routes->add('nc.en', new Route('/en/nc', [], [], [], '{sub}.example.test'));
        yield 'route x.en with a host variable, not a copy of x' => [$routes, 'nc'];

        $routes = new RouteCollection();
        $routes->add('nca.en', new Route('/en/nca', [], [], [], '{sub}.example.test'));
        yield 'route x.en with a host variable, not a copy, no x' => [$routes, 'nca'];
    }

    #[DataProvider('namesTheGeneratorsReadDifferently')]
    public function testNoLinkLeavesTheSiteWhicheverRouteTheGeneratorReads(RouteCollection $routes, string $name): void
    {
        foreach ($this->generators($routes) as $class => $generator) {
            $router = $this->createMock(RouterInterface::class);
            $router->method('getRouteCollection')->willReturn($routes);
            $resolver = new BreadcrumbUrlResolver($generator, new RequestStack(), $router);

            foreach (['evil.test#', 'evil#', 'evil/'] as $value) {
                self::assertNull($resolver->resolve($name, ['sub' => $value], null)[0], $class.' with '.$value);
            }
        }
    }

    public function testCopyOfARouteDecidesAloneEvenBesideATighterBaseRoute(): void
    {
        $routes = new RouteCollection();
        $routes->add('loc', new Route('/loc/{id}', [], ['id' => '\d+']));
        $routes->add('loc.en', new Route('/en/{id}', ['_canonical_route' => 'loc', '_locale' => 'en'], ['_locale' => 'en']));

        self::assertSame('/en/abc', $this->resolverFor($routes, 'en', null)->resolve('loc', ['id' => 'abc'], null)[0]);
    }

    public function testRegionalCopyFallsBackToTheLanguageCopyPastAnUnrelatedRoute(): void
    {
        $routes = new RouteCollection();
        $routes->add('loc.en_GB', new Route('/unrelated/{id}'));
        $routes->add('loc.en', new Route('/en/{id}', ['_canonical_route' => 'loc', '_locale' => 'en'], ['id' => '\d+', '_locale' => 'en']));

        $resolver = $this->resolverFor($routes, 'en_GB', null);

        self::assertNull($resolver->resolve('loc', ['id' => 'abc'], null)[0]);
        self::assertSame('/en/5', $resolver->resolve('loc', ['id' => '5'], null)[0]);
    }

    /**
     * HD-I16: both generators cast a scalar `_locale`, so `li.5` is the route they use.
     *
     * @return iterable<string, array{int|bool|float, string}>
     */
    public static function scalarLocales(): iterable
    {
        yield 'integer' => [5, 'li.5'];
        yield 'true' => [true, 'li.1'];
        yield 'float' => [1.5, 'li.1.5'];
    }

    #[DataProvider('scalarLocales')]
    public function testScalarLocaleParameterFindsTheRouteTheGeneratorUses(int|bool|float $locale, string $copy): void
    {
        $routes = new RouteCollection();
        $routes->add('li', new Route('/li/{id}'));
        $routes->add($copy, new Route('/li5/{id}', ['_canonical_route' => 'li'], ['id' => '\d+']));

        foreach ($this->generators($routes) as $class => $generator) {
            $router = $this->createMock(RouterInterface::class);
            $router->method('getRouteCollection')->willReturn($routes);
            $resolver = new BreadcrumbUrlResolver($generator, new RequestStack(), $router);

            self::assertNull($resolver->resolve('li', ['id' => 'abc', '_locale' => $locale], null)[0], $class);
            self::assertStringStartsWith('/li5/5', (string) $resolver->resolve('li', ['id' => '5', '_locale' => $locale], null)[0], $class);
        }
    }

    /**
     * HD-I17: the dashboard form's JSON field does not filter to scalars.
     */
    public function testArrayLocaleGivesNoLinkInsteadOfATypeError(): void
    {
        $routes = new RouteCollection();
        $routes->add('plain', new Route('/plain'));

        foreach ($this->generators($routes) as $class => $generator) {
            $router = $this->createMock(RouterInterface::class);
            $router->method('getRouteCollection')->willReturn($routes);
            $resolver = new BreadcrumbUrlResolver($generator, new RequestStack(), $router);

            // @: array to string conversion on the way to the TypeError, a warning this test is not about.
            self::assertNull(@$resolver->resolve('plain', self::paramsTheFormCanStore(), null)[0], $class);
        }
    }

    public function testAnErrorFromTheGeneratorGivesNoLink(): void
    {
        $generator = $this->createMock(UrlGeneratorInterface::class);
        $generator->method('generate')->willThrowException(new TypeError('from the generator'));
        $routes = new RouteCollection();
        $routes->add('plain', new Route('/plain'));
        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routes);

        $result = (new BreadcrumbUrlResolver($generator, new RequestStack(), $router))->resolve('plain', ['a' => 1], null);

        self::assertSame([null, ['a' => 1]], $result);
    }

    /**
     * @return array<string, mixed>
     */
    private static function paramsTheFormCanStore(): array
    {
        return ['_locale' => ['en']];
    }

    /**
     * @return array<string, UrlGeneratorInterface> the two a Symfony application can run on, under the production recipe
     */
    private function generators(RouteCollection $routes): array
    {
        $plain = new UrlGenerator($routes, new RequestContext());
        $plain->setStrictRequirements(null);
        $context = new RequestContext();
        $context->setParameter('_locale', 'en');
        $plain->setContext($context);
        $compiled = new CompiledUrlGenerator((new CompiledUrlGeneratorDumper($routes))->getCompiledRoutes(), $context);
        $compiled->setStrictRequirements(null);

        return ['UrlGenerator' => $plain, 'CompiledUrlGenerator' => $compiled];
    }

    private function resolverFor(RouteCollection $routes, ?string $contextLocale, ?string $defaultLocale): BreadcrumbUrlResolver
    {
        $context = new RequestContext();
        if (null !== $contextLocale) {
            $context->setParameter('_locale', $contextLocale);
        }
        $generator = new UrlGenerator($routes, $context, null, $defaultLocale);
        $generator->setStrictRequirements(null);
        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routes);

        return new BreadcrumbUrlResolver($generator, new RequestStack(), $router, $defaultLocale);
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
