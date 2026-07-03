<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Form\Dashboard;

use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Empty POST form: CSRF only. Built with {@see \Symfony\Bundle\FrameworkBundle\Controller\AbstractController::createNamedBuilder()}
 * so each row has a unique name and {@code csrf_token_id}.
 */
final class DashboardPostDeleteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'POST',
            'csrf_message' => 'form.delete.csrf_invalid',
            'translation_domain' => NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN,
        ]);
        $resolver->setRequired('csrf_token_id');
        $resolver->setAllowedTypes('csrf_token_id', 'string');
    }
}
