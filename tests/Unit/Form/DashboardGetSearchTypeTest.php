<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Form;

use Nowo\BreadcrumbKitBundle\Form\Dashboard\DashboardGetSearchType;
use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

final class DashboardGetSearchTypeTest extends TestCase
{
    public function testBuildFormAddsSearchField(): void
    {
        $merger = new FormOptionsMerger(
            [
                'breadcrumb_kit' => [
                    'translation_domain' => 'NowoBreadcrumbKitBundle',
                    'defaults' => [
                        'attr' => ['class' => 'form-control'],
                        'row_attr' => ['class' => 'mb-2'],
                    ],
                    'field_types' => [],
                ],
            ],
            'breadcrumb_kit',
            new ConstraintDefinitionFactory(),
        );

        $type = new DashboardGetSearchType();
        $type->setFormOptionsMerger($merger);

        $factory = Forms::createFormFactoryBuilder()
            ->addType($type)
            ->getFormFactory();

        $form = $factory->create(DashboardGetSearchType::class, null, [
            'search_placeholder' => 'Search…',
        ]);

        self::assertTrue($form->has('q'));
        self::assertSame('GET', $form->getConfig()->getMethod());
    }
}
