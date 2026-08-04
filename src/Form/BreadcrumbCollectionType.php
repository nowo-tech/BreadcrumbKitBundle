<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Form;

use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Form\DataTransformer\JsonObjectTransformer;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<BreadcrumbCollection>
 */
#[FormKitConfig('breadcrumb_kit')]
final class BreadcrumbCollectionType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addTextField('code', [
                'label' => 'form.breadcrumb_collection.code.label',
                'placeholder' => false,
                'help' => false,
                'constraints' => [new NotBlank(), new Length(max: 64)],
                'attr' => ['maxlength' => 64],
            ]);
            $this->addTextField('contextKey', [
                'label' => 'form.breadcrumb_collection.context_key.label',
                'placeholder' => false,
                'help' => 'form.breadcrumb_collection.context_key.help',
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Length(max: 512)],
            ]);
            $this->addTextField('name', [
                'label' => 'form.breadcrumb_collection.name.label',
                'placeholder' => false,
                'help' => false,
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ]);
            $this->addTextField('homeIcon', [
                'label' => 'form.breadcrumb_collection.home_icon.label',
                'placeholder' => false,
                'help' => 'form.breadcrumb_collection.home_icon.help',
                'required' => false,
                'constraints' => [new Length(max: 128)],
            ]);
            $this->addTextField('separatorIcon', [
                'label' => 'form.breadcrumb_collection.separator_icon.label',
                'placeholder' => false,
                'help' => false,
                'required' => false,
                'constraints' => [new Length(max: 128)],
            ]);
            $this->addTextField('classList', [
                'label' => 'form.breadcrumb_collection.class_list.label',
                'placeholder' => false,
                'help' => false,
                'required' => false,
                'constraints' => [new Length(max: 512)],
                'attr' => ['placeholder' => 'form.breadcrumb_collection.class_list.placeholder'],
            ]);
            $this->addTextField('classItem', [
                'label' => 'form.breadcrumb_collection.class_item.label',
                'placeholder' => false,
                'help' => false,
                'required' => false,
                'constraints' => [new Length(max: 512)],
            ]);
            $this->addTextField('classSeparator', [
                'label' => 'form.breadcrumb_collection.class_separator.label',
                'placeholder' => false,
                'help' => false,
                'required' => false,
                'constraints' => [new Length(max: 512)],
            ]);
            $this->addTextField('classCurrent', [
                'label' => 'form.breadcrumb_collection.class_current.label',
                'placeholder' => false,
                'help' => false,
                'required' => false,
                'constraints' => [new Length(max: 512)],
            ]);
            $this->addTextareaField('responsiveConfigJson', [
                'label' => 'form.breadcrumb_collection.responsive_config.label',
                'placeholder' => false,
                'help' => 'form.breadcrumb_collection.responsive_config.help',
                'required' => false,
                'mapped' => false,
                'attr' => ['rows' => 6, 'class' => 'monospace', 'spellcheck' => 'false'],
            ]);

            $inlineKeys = $options['inline_edit_access_keys'];
            if ([] !== $inlineKeys) {
                $choices = [];
                foreach ($inlineKeys as $k) {
                    if (\is_string($k) && '' !== $k) {
                        $choices[$k] = $k;
                    }
                }
                if ([] !== $choices) {
                    $this->addChoiceField('inlineEditAccessKey', [
                        'label' => 'form.breadcrumb_collection.inline_edit_access.label',
                        'placeholder' => 'form.breadcrumb_collection.inline_edit_access.placeholder',
                        'help' => 'form.breadcrumb_collection.inline_edit_access.help',
                        'required' => false,
                        'choices' => $choices,
                    ]);
                }
            }
        });

        $builder->get('responsiveConfigJson')->addModelTransformer(new JsonObjectTransformer());

        $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event): void {
            $collection = $event->getData();
            if (!$collection instanceof BreadcrumbCollection) {
                return;
            }
            $event->getForm()->get('responsiveConfigJson')->setData($collection->getResponsiveConfig() ?? []);
        });

        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
            $collection = $event->getData();
            if (!$collection instanceof BreadcrumbCollection) {
                return;
            }
            $raw = $event->getForm()->get('responsiveConfigJson')->getData();
            $collection->setResponsiveConfig(\is_array($raw) && [] !== $raw ? $raw : null);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BreadcrumbCollection::class,
            'translation_domain' => NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN,
            'inline_edit_access_keys' => [],
        ]);
    }
}
