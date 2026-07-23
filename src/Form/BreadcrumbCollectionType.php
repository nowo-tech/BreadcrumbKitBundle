<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Form;

use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Form\DataTransformer\JsonObjectTransformer;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<BreadcrumbCollection>
 */
final class BreadcrumbCollectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'form.breadcrumb_collection.code.label',
                'constraints' => [new NotBlank(), new Length(max: 64)],
                'attr' => ['maxlength' => 64],
            ])
            ->add('contextKey', TextType::class, [
                'label' => 'form.breadcrumb_collection.context_key.label',
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Length(max: 512)],
                'help' => 'form.breadcrumb_collection.context_key.help',
            ])
            ->add('name', TextType::class, [
                'label' => 'form.breadcrumb_collection.name.label',
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('homeIcon', TextType::class, [
                'label' => 'form.breadcrumb_collection.home_icon.label',
                'required' => false,
                'constraints' => [new Length(max: 128)],
                'help' => 'form.breadcrumb_collection.home_icon.help',
            ])
            ->add('separatorIcon', TextType::class, [
                'label' => 'form.breadcrumb_collection.separator_icon.label',
                'required' => false,
                'constraints' => [new Length(max: 128)],
            ])
            ->add('classList', TextType::class, [
                'label' => 'form.breadcrumb_collection.class_list.label',
                'required' => false,
                'constraints' => [new Length(max: 512)],
                'attr' => ['placeholder' => 'form.breadcrumb_collection.class_list.placeholder'],
            ])
            ->add('classItem', TextType::class, [
                'label' => 'form.breadcrumb_collection.class_item.label',
                'required' => false,
                'constraints' => [new Length(max: 512)],
            ])
            ->add('classSeparator', TextType::class, [
                'label' => 'form.breadcrumb_collection.class_separator.label',
                'required' => false,
                'constraints' => [new Length(max: 512)],
            ])
            ->add('classCurrent', TextType::class, [
                'label' => 'form.breadcrumb_collection.class_current.label',
                'required' => false,
                'constraints' => [new Length(max: 512)],
            ])
            ->add('responsiveConfigJson', TextareaType::class, [
                'label' => 'form.breadcrumb_collection.responsive_config.label',
                'required' => false,
                'mapped' => false,
                'attr' => ['rows' => 6, 'class' => 'monospace', 'spellcheck' => 'false'],
                'help' => 'form.breadcrumb_collection.responsive_config.help',
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
                $builder->add('inlineEditAccessKey', ChoiceType::class, [
                    'label' => 'form.breadcrumb_collection.inline_edit_access.label',
                    'required' => false,
                    'placeholder' => 'form.breadcrumb_collection.inline_edit_access.placeholder',
                    'choices' => $choices,
                    'help' => 'form.breadcrumb_collection.inline_edit_access.help',
                ]);
            }
        }

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
