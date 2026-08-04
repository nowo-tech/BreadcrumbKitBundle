<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Form\Dashboard;

use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Subida JSON + estrategia (paridad con DashboardMenuBundle).
 *
 * @extends AbstractType<array{file?: UploadedFile, strategy?: string}>
 */
#[FormKitConfig('breadcrumb_kit')]
final class ImportBreadcrumbType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addWithDefaults($this->boundBuilder(), 'file', FileType::class, [
                'required' => true,
                'label' => 'form.import_breadcrumb.file.label',
                'placeholder' => false,
                'help' => false,
                'attr' => ['accept' => '.json,application/json'],
                'constraints' => [
                    new NotBlank(message: 'form.import_breadcrumb.file.required'),
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['application/json', 'text/plain'],
                        mimeTypesMessage: 'form.import_breadcrumb.file.mime',
                    ),
                ],
            ]);
            $this->addChoiceField('strategy', [
                'required' => true,
                'label' => 'form.import_breadcrumb.strategy.label',
                'placeholder' => false,
                'help' => false,
                'choices' => [
                    'form.import_breadcrumb.strategy.skip' => BreadcrumbImporter::STRATEGY_SKIP_EXISTING,
                    'form.import_breadcrumb.strategy.replace' => BreadcrumbImporter::STRATEGY_REPLACE,
                ],
                'data' => BreadcrumbImporter::STRATEGY_SKIP_EXISTING,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'POST',
            'translation_domain' => NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN,
        ]);
    }
}
