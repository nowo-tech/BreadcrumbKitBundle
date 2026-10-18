<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Controller\Dashboard;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Controller\Dashboard\CollectionCrudController;
use Nowo\BreadcrumbKitBundle\Dashboard\DashboardPages;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbExporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class DashboardControllerTraitTest extends TestCase
{
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
            new DashboardPages($this->createMock(UrlGeneratorInterface::class), new Environment(new ArrayLoader())),
        );

        $method = new \ReflectionMethod($controller, 'createDeletePostForm');
        $form = $method->invoke($controller, '/delete/42', 'delete_collection_42');
        $view = $form->createView();

        self::assertSame('', $form->getName());
        self::assertSame('/delete/42', $form->getConfig()->getAction());
        self::assertSame('POST', $form->getConfig()->getMethod());
        self::assertSame('_token', $view['_token']->vars['full_name']);
    }
}
