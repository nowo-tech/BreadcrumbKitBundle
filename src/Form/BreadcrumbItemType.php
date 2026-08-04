<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Form;

use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Form\DataTransformer\JsonObjectTransformer;
use Nowo\BreadcrumbKitBundle\Form\DataTransformer\JsonStringListTransformer;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<BreadcrumbItem>
 */
#[FormKitConfig('breadcrumb_kit')]
final class BreadcrumbItemType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var BreadcrumbCollection $collection */
        $collection = $options['collection'];
        /** @var BreadcrumbItem|null $excludeItem */
        $excludeItem = $options['exclude_item'];

        $this->withBuilder($builder, function () use ($collection, $excludeItem): void {
            $this->addTextField('routeName', [
                'label' => 'form.breadcrumb_item.route_name.label',
                'placeholder' => false,
                'help' => false,
                'constraints' => [new NotBlank(message: 'form.breadcrumb_item.route_name.not_blank'), new Length(max: 255)],
                'attr' => ['placeholder' => 'app_product_show'],
            ]);
            // Keep JsonObjectTransformer: empty → [] / null-friendly UX (FormKit JsonModelTransformer encodes null as "null").
            $this->addTextareaField('staticRouteParams', [
                'label' => 'form.breadcrumb_item.static_params.label',
                'placeholder' => false,
                'help' => 'form.breadcrumb_item.static_params.help',
                'required' => false,
                'attr' => ['rows' => 3, 'class' => 'font-monospace', 'spellcheck' => 'false'],
            ]);
            $this->addTextareaField('dynamicParamKeys', [
                'label' => 'form.breadcrumb_item.dynamic_keys.label',
                'placeholder' => false,
                'help' => 'form.breadcrumb_item.dynamic_keys.help',
                'required' => false,
                'attr' => ['rows' => 2, 'class' => 'font-monospace', 'spellcheck' => 'false'],
            ]);
            $this->addCheckboxField('linkEnabled', [
                'label' => 'form.breadcrumb_item.link_enabled.label',
                'placeholder' => false,
                'help' => false,
                'required' => false,
            ]);
            $this->addTextField('label', [
                'label' => 'form.breadcrumb_item.label.label',
                'placeholder' => false,
                'help' => false,
                'required' => false,
                'constraints' => [new Length(max: 512)],
            ]);
            $this->addTextareaField('translations', [
                'label' => 'form.breadcrumb_item.translations.label',
                'placeholder' => false,
                'help' => 'form.breadcrumb_item.translations.help',
                'required' => false,
                'attr' => ['rows' => 4, 'class' => 'font-monospace', 'spellcheck' => 'false'],
            ]);
            $this->addTextField('icon', [
                'label' => 'form.breadcrumb_item.icon.label',
                'placeholder' => false,
                'help' => false,
                'required' => false,
                'constraints' => [new Length(max: 128)],
            ]);
            $this->addWithDefaults($this->boundBuilder(), 'parent', EntityType::class, [
                'class' => BreadcrumbItem::class,
                'label' => 'form.breadcrumb_item.parent.label',
                'placeholder' => 'form.breadcrumb_item.parent.placeholder',
                'help' => false,
                'required' => false,
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
        });

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
