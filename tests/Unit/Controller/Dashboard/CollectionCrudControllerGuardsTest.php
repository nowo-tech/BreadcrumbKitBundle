<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Controller\Dashboard;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Nowo\BreadcrumbKitBundle\Controller\Dashboard\CollectionCrudController;
use Nowo\BreadcrumbKitBundle\Dashboard\DashboardPages;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbExporter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Form\Forms;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The defensive branches of the collection controller that a request cannot reach: the inline-edit parameter that is not a
 * map, and a unique-key violation wrapped in another exception.
 */
final class CollectionCrudControllerGuardsTest extends TestCase
{
    public function testTheInlineEditKeysAreEmptyWhenTheParameterIsNotAMap(): void
    {
        $controller = $this->controller(new ParameterBag(['nowo_breadcrumb_kit.inline_edit.access_services' => 'not-a-map']));

        self::assertSame(['inline_edit_access_keys' => []], (new ReflectionMethod($controller, 'collectionFormOptions'))->invoke($controller));
    }

    public function testOnlyNonEmptyStringKeysAreOffered(): void
    {
        $controller = $this->controller(new ParameterBag(['nowo_breadcrumb_kit.inline_edit.access_services' => ['admin' => 'a', '' => 'b', 3 => 'c']]));

        self::assertSame(['inline_edit_access_keys' => ['admin']], (new ReflectionMethod($controller, 'collectionFormOptions'))->invoke($controller));
    }

    public function testAUniqueViolationWrappedInAnotherExceptionIsRecognised(): void
    {
        $controller = $this->controller(new ParameterBag());
        $method = new ReflectionMethod($controller, 'isUniqueConstraintViolation');
        $driver = new class('duplicate') extends Exception implements DriverException {
            public function getSQLState(): string
            {
                return '23000';
            }
        };

        self::assertTrue($method->invoke($controller, new UniqueConstraintViolationException($driver, null)));
        self::assertTrue($method->invoke($controller, new RuntimeException('wrapper', 0, new UniqueConstraintViolationException($driver, null))));
        self::assertFalse($method->invoke($controller, new RuntimeException('other', 0, new RuntimeException('inner'))));
    }

    private function controller(ParameterBag $parameters): CollectionCrudController
    {
        return new CollectionCrudController(
            $this->createMock(BreadcrumbCollectionRepository::class),
            $this->createMock(BreadcrumbItemRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $parameters,
            new BreadcrumbExporter($this->createMock(BreadcrumbCollectionRepository::class), $this->createMock(BreadcrumbItemRepository::class)),
            Forms::createFormFactory(),
            $this->createMock(TranslatorInterface::class),
            false,
            25,
            new DashboardPages($this->createMock(UrlGeneratorInterface::class), new Environment(new ArrayLoader())),
        );
    }
}
