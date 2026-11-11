<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Dto;

use Nowo\BreadcrumbKitBundle\Service\BreadcrumbRequestContextFactory;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class BreadcrumbEditContextTest extends TestCase
{
    public function testAnonymousContextHasNoIdentityAndNoRoles(): void
    {
        $request = Request::create('/en/projects/7?edit=1');
        $request->attributes->set('_route', 'project_show');
        $request->attributes->set('_route_params', ['id' => '7', 'list' => ['x'], 3 => 'positional']);
        $request->setLocale('en');

        $context = BreadcrumbRequestContextFactory::edit($request, null, 'main', '');

        self::assertFalse($context->isAuthenticated());
        self::assertNull($context->userIdentifier);
        self::assertSame([], $context->roles);
        self::assertFalse($context->hasRole('ROLE_USER'));
        self::assertSame('project_show', $context->routeName);
        self::assertSame(['id' => '7'], $context->routeParameters);
        self::assertSame('en', $context->locale);
        self::assertSame('/en/projects/7', $context->path);
        self::assertSame('main', $context->collectionCode);
        self::assertSame('', $context->contextKey);
    }

    public function testAuthenticatedContextCarriesIdentifierAndRoles(): void
    {
        $request = new Request(attributes: ['_route' => ['not', 'a', 'string'], '_route_params' => new stdClass()]);

        $context = BreadcrumbRequestContextFactory::edit($request, new InMemoryUser('alice', null, ['ROLE_EDITOR']), 'main', 'tenant-a');

        self::assertTrue($context->isAuthenticated());
        self::assertSame('alice', $context->userIdentifier);
        self::assertSame(['ROLE_EDITOR'], $context->roles);
        self::assertTrue($context->hasRole('ROLE_EDITOR'));
        self::assertFalse($context->hasRole('role_editor'), 'roles compare exactly');
        self::assertNull($context->routeName);
        self::assertSame([], $context->routeParameters);
        self::assertSame('tenant-a', $context->contextKey);
    }
}
