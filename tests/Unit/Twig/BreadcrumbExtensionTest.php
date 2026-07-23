<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Twig;

use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbInlineEditAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbInlineEditResolver;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolverInterface;
use Nowo\BreadcrumbKitBundle\Twig\BreadcrumbExtension;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class BreadcrumbExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersTrailAndRender(): void
    {
        $extension = new BreadcrumbExtension(
            $this->minimalLoader(),
            $this->minimalInlineResolver(),
            'default',
        );

        $names = array_map(static fn ($fn) => $fn->getName(), $extension->getFunctions());

        self::assertSame(['breadcrumb_trail', 'breadcrumb_render'], $names);
    }

    public function testTrailUsesDefaultCollectionWhenCodeIsNull(): void
    {
        $collection = $this->collectionWithId(1);
        $item = $this->itemWithId(1, $collection, null, 'home', 'Home');
        $item->setLinkEnabled(false);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->with('default', '')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$item]);

        $request = Request::create('/');
        $request->attributes->set('_route', 'home');
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

        $extension = new BreadcrumbExtension($loader, $this->minimalInlineResolver(), 'default');
        $nodes = $extension->trail();

        self::assertCount(1, $nodes);
        self::assertSame('Home', $nodes[0]->label);
    }

    public function testTrailUsesExplicitCollectionCode(): void
    {
        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->expects(self::once())
            ->method('findOneByCodeAndContextKey')
            ->with('admin', 'shop')
            ->willReturn(null);

        $request = Request::create('/');
        $request->attributes->set('_route', 'x');
        $stack = new RequestStack();
        $stack->push($request);

        $loader = new BreadcrumbLoader(
            $colRepo,
            $this->createMock(BreadcrumbItemRepository::class),
            $this->createMock(BreadcrumbUrlResolverInterface::class),
            $stack,
            'en',
        );

        $extension = new BreadcrumbExtension($loader, $this->minimalInlineResolver(), 'default');

        self::assertSame([], $extension->trail('admin', 'shop'));
    }

    public function testRenderPassesTrailAndInlineEditContextToTwig(): void
    {
        $collection = $this->collectionWithId(1);
        $item = $this->itemWithId(1, $collection, null, 'page', 'Page');
        $item->setLinkEnabled(false);

        $colRepo = $this->createMock(BreadcrumbCollectionRepository::class);
        $colRepo->method('findOneByCodeAndContextKey')->willReturn($collection);
        $itemRepo = $this->createMock(BreadcrumbItemRepository::class);
        $itemRepo->method('findAllForCollection')->willReturn([$item]);

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

        $collection->setInlineEditAccessKey('admin');
        $checker = new class implements BreadcrumbInlineEditAccessCheckerInterface {
            public function canUseInlineBreadcrumbEditor(Request $request, ?UserInterface $user): bool
            {
                return true;
            }
        };
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($checker);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/iframe');

        $inlineResolver = new BreadcrumbInlineEditResolver(
            $stack,
            $colRepo,
            $loader,
            $locator,
            $urlGenerator,
            $this->createMock(TranslatorInterface::class),
            null,
            'edit',
            true,
        );

        $twig = new Environment(new ArrayLoader([
            'tpl' => 'nodes={{ trail.nodes|length }} show={{ breadcrumb_inline_edit.show }} url={{ breadcrumb_inline_edit.iframe_url }}',
        ]));

        $extension = new BreadcrumbExtension($loader, $inlineResolver, 'default');
        $html = $extension->render($twig, 'default', 'tpl', '');

        self::assertSame('nodes=1 show=1 url=/iframe', trim($html));
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

    private function minimalInlineResolver(): BreadcrumbInlineEditResolver
    {
        return new BreadcrumbInlineEditResolver(
            new RequestStack(),
            $this->createMock(BreadcrumbCollectionRepository::class),
            $this->minimalLoader(),
            $this->createMock(ContainerInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(TranslatorInterface::class),
            null,
            null,
            false,
        );
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
}
