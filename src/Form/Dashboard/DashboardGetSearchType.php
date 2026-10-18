<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Form\Dashboard;

use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * GET search form: query param {@code q} at the root (empty block prefix). {@code search_placeholder} is a translation
 * key of the form domain (the form theme translates the attribute); {@code search_label} is printed as given
 * ({@code aria-label} is not translated by the theme).
 *
 * @extends AbstractType<array{q?: string|null}>
 */
#[FormKitConfig('breadcrumb_kit')]
final class DashboardGetSearchType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Plain builder->add(): the FormKit field conventions would give the label-less field a "Q" label.
        $builder->add('q', SearchType::class, [
            'label' => false,
            'required' => false,
            'attr' => array_filter([
                'placeholder' => $options['search_placeholder'],
                'aria-label' => $options['search_label'],
                'autocomplete' => 'off',
            ], static fn (string $v): bool => '' !== $v),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // search_placeholder is a message of this domain, translated once by the form theme.
            'translation_domain' => NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN,
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
