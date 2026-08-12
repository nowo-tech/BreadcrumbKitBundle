<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Controller\Dashboard;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Controller\Dashboard\CollectionCrudController;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbExporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DashboardControllerTraitTest extends TestCase
{
    public function testResolveModalClassesMapsSizes(): void
    {
        $method = new \ReflectionMethod(CollectionCrudController::class, 'resolveModalClasses');

        $classes = $method->invoke(null, [
            'collection_form' => 'lg',
            'item_form' => 'xl',
            'import' => 'normal',
            'delete' => 'normal',
        ]);

        self::assertSame('modal-lg', $classes['collection_form']);
        self::assertSame('modal-xl', $classes['item_form']);
        self::assertSame('', $classes['import']);
        self::assertSame('', $classes['delete']);
    }

    public function testResolveModalClassesUsesDefaultsForMissingKeys(): void
    {
        $method = new \ReflectionMethod(CollectionCrudController::class, 'resolveModalClasses');

        $classes = $method->invoke(null, []);

        self::assertSame('modal-lg', $classes['collection_form']);
        self::assertSame('modal-lg', $classes['item_form']);
        self::assertSame('', $classes['import']);
        self::assertSame('', $classes['delete']);
    }

    public function testCreateDeletePostFormUsesUnnamedRootTokenField(): void
    {
        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension(new CsrfTokenManager()))
            ->getFormFactory();

        $controller = new CollectionCrudController(
            $this->createMock(BreadcrumbCollectionRepository::class),
            $this->createMock(BreadcrumbItemRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(ParameterBagInterface::class),
            new BreadcrumbExporter(
                $this->createMock(BreadcrumbCollectionRepository::class),
                $this->createMock(BreadcrumbItemRepository::class),
            ),
            $formFactory,
            $this->createMock(TranslatorInterface::class),
            false,
            25,
            [],
        );

        $method = new \ReflectionMethod($controller, 'createDeletePostForm');
        $method->setAccessible(true);
        $form = $method->invoke($controller, '/delete/42', 'delete_collection_42');
        $view = $form->createView();

        self::assertSame('', $form->getName());
        self::assertSame('/delete/42', $form->getConfig()->getAction());
        self::assertSame('POST', $form->getConfig()->getMethod());
        self::assertSame('_token', $view['_token']->vars['full_name']);
    }
}

