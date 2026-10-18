<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Form\Dashboard;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * GET search form: query param {@code q} at the root (empty block prefix).
 *
 * @extends AbstractType<array{q?: string|null}>
 */
#[FormKitConfig('breadcrumb_kit')]
final class DashboardGetSearchType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addWithDefaults($this->boundBuilder(), 'q', SearchType::class, [
                'label' => false,
                'placeholder' => false,
                'help' => false,
                'required' => false,
                'attr' => array_filter([
                    'placeholder' => $options['search_placeholder'],
                    'aria-label' => $options['search_label'],
                    'autocomplete' => 'off',
                ], static fn (string $v): bool => '' !== $v),
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
            'allow_extra_fields' => true,
            'search_placeholder' => '',
            'search_label' => '',
        ]);
        $resolver->setAllowedTypes('search_placeholder', 'string');
        $resolver->setAllowedTypes('search_label', 'string');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
