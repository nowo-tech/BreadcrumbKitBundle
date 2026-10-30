<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Form\Dashboard;

use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * POST form: CSRF token + a hidden {@code confirm} marker. Built unnamed with {@see AbstractController::createNamedBuilder()}
 * (fields {@code _token} and {@code confirm} at the root) and a per-row {@code csrf_token_id}. The marker is what makes
 * Symfony submit an unnamed root form (it ignores the CSRF field when it looks for submitted children).
 *
 * @extends AbstractType<null>
 */
#[FormKitConfig('breadcrumb_kit')]
final class DashboardPostDeleteType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('confirm', HiddenType::class, ['mapped' => false, 'data' => '1']);
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
