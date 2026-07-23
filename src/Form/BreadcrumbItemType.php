<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Form;

use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Form\DataTransformer\JsonObjectTransformer;
use Nowo\BreadcrumbKitBundle\Form\DataTransformer\JsonStringListTransformer;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<BreadcrumbItem>
 */
final class BreadcrumbItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var BreadcrumbCollection $collection */
        $collection = $options['collection'];
        /** @var BreadcrumbItem|null $excludeItem */
        $excludeItem = $options['exclude_item'];

        $builder
            ->add('routeName', TextType::class, [
                'label' => 'form.breadcrumb_item.route_name.label',
                'constraints' => [new NotBlank(message: 'form.breadcrumb_item.route_name.not_blank'), new Length(max: 255)],
                'attr' => ['placeholder' => 'app_product_show'],
            ])
            ->add('staticRouteParams', TextareaType::class, [
                'label' => 'form.breadcrumb_item.static_params.label',
                'required' => false,
                'attr' => ['rows' => 3, 'class' => 'monospace', 'spellcheck' => 'false'],
                'help' => 'form.breadcrumb_item.static_params.help',
            ])
            ->add('dynamicParamKeys', TextareaType::class, [
                'label' => 'form.breadcrumb_item.dynamic_keys.label',
                'required' => false,
                'attr' => ['rows' => 2, 'class' => 'monospace', 'spellcheck' => 'false'],
                'help' => 'form.breadcrumb_item.dynamic_keys.help',
            ])
            ->add('linkEnabled', CheckboxType::class, [
                'label' => 'form.breadcrumb_item.link_enabled.label',
                'required' => false,
            ])
            ->add('label', TextType::class, [
                'label' => 'form.breadcrumb_item.label.label',
                'required' => false,
                'constraints' => [new Length(max: 512)],
            ])
            ->add('translations', TextareaType::class, [
                'label' => 'form.breadcrumb_item.translations.label',
                'required' => false,
                'attr' => ['rows' => 4, 'class' => 'monospace', 'spellcheck' => 'false'],
                'help' => 'form.breadcrumb_item.translations.help',
            ])
            ->add('icon', TextType::class, [
                'label' => 'form.breadcrumb_item.icon.label',
                'required' => false,
                'constraints' => [new Length(max: 128)],
            ])
            ->add('parent', EntityType::class, [
                'class' => BreadcrumbItem::class,
                'label' => 'form.breadcrumb_item.parent.label',
                'required' => false,
                'placeholder' => 'form.breadcrumb_item.parent.placeholder',
                'choice_label' => static function (BreadcrumbItem $item): string {
                    $l = $item->getLabel() ?: $item->getRouteName();

                    return $l.' (#'.(string) ($item->getId() ?? '?').')';
                },
                'query_builder' => static function (BreadcrumbItemRepository $repository) use ($collection, $excludeItem) {
                    $qb = $repository->createQueryBuilder('i')
                        ->andWhere('i.collection = :c')
                        ->setParameter('c', $collection)
                        ->orderBy('i.id', 'ASC');
                    if ($excludeItem instanceof BreadcrumbItem && null !== $excludeItem->getId()) {
                        $qb->andWhere('i.id != :xid')->setParameter('xid', $excludeItem->getId());
                    }

                    return $qb;
                },
            ]);

        $builder->get('staticRouteParams')->addModelTransformer(new JsonObjectTransformer());
        $builder->get('dynamicParamKeys')->addModelTransformer(new JsonStringListTransformer());
        $builder->get('translations')->addModelTransformer(new JsonObjectTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BreadcrumbItem::class,
            'translation_domain' => NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN,
            'collection' => null,
            'exclude_item' => null,
        ]);
        $resolver->setAllowedTypes('collection', [BreadcrumbCollection::class]);
        $resolver->setAllowedTypes('exclude_item', ['null', BreadcrumbItem::class]);
    }
}
