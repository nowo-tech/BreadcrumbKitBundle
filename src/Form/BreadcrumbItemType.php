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
use Nowo\FormKitBundle\Form\Type\TranslatableMapType;
use SortDirection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
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

    /**
     * @param list<string> $locales tabs of the label translations (nowo_breadcrumb_kit.locales; [] = FormKit locales,
     *                              i.e. framework.enabled_locales)
     */
    public function __construct(
        private readonly array $locales = [],
        private readonly ?string $defaultLocale = null,
    ) {
    }

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
                'attr' => ['placeholder' => 'form.breadcrumb_item.route_name.placeholder'],
            ]);
            // Keep JsonObjectTransformer: empty → [] / null-friendly UX (FormKit JsonModelTransformer encodes null as "null").
            $this->addTextareaField('staticRouteParams', [
                'label' => 'form.breadcrumb_item.static_params.label',
                'placeholder' => false,
                'help' => 'form.breadcrumb_item.static_params.help',
                'required' => false,
                'attr' => ['rows' => 3, 'class' => 'nowo-ui-input nowo-ui-input--full nowo-ui-code', 'spellcheck' => 'false'],
            ]);
            $this->addTextField('pathPattern', [
                'label' => 'form.breadcrumb_item.path_pattern.label',
                'placeholder' => false,
                'help' => 'form.breadcrumb_item.path_pattern.help',
                'required' => false,
                'constraints' => [new Length(max: 512)],
                'attr' => ['placeholder' => 'form.breadcrumb_item.path_pattern.placeholder', 'class' => 'nowo-ui-input nowo-ui-input--full nowo-ui-code'],
            ]);
            $this->addTextareaField('matchAttributes', [
                'label' => 'form.breadcrumb_item.match_attributes.label',
                'placeholder' => false,
                'help' => 'form.breadcrumb_item.match_attributes.help',
                'required' => false,
                'attr' => ['rows' => 2, 'class' => 'nowo-ui-input nowo-ui-input--full nowo-ui-code', 'spellcheck' => 'false'],
            ]);
            $this->addTextareaField('dynamicParamKeys', [
                'label' => 'form.breadcrumb_item.dynamic_keys.label',
                'placeholder' => false,
                'help' => 'form.breadcrumb_item.dynamic_keys.help',
                'required' => false,
                'attr' => ['rows' => 2, 'class' => 'nowo-ui-input nowo-ui-input--full nowo-ui-code', 'spellcheck' => 'false'],
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
            // 2.8: one tab per locale (FormKit TranslatableMapType, array input = the entity's JSON column). Keys of
            // locales that are not configured stay in the map on save (see finishView for the notice).
            $mapOptions = [
                'label' => 'form.breadcrumb_item.translations.label',
                'help' => 'form.breadcrumb_item.translations.help',
                'required' => false,
                'entry_options' => ['attr' => ['class' => 'nowo-ui-input nowo-ui-input--full'], 'constraints' => [new Length(max: 512)]],
            ];
            if ([] !== $this->locales) {
                $mapOptions['locales'] = $this->locales;
            }
            if (null !== $this->defaultLocale && ([] === $this->locales || \in_array($this->defaultLocale, $this->locales, true))) {
                $mapOptions['default_locale'] = $this->defaultLocale;
            }
            $this->addWithDefaults($this->boundBuilder(), 'translations', TranslatableMapType::class, $mapOptions);
            // The entity stores null for "no translations" (as with the 2.7 JSON textarea).
            $this->boundBuilder()->get('translations')->addModelTransformer(new CallbackTransformer(
                static fn (mixed $map): mixed => $map,
                static fn (mixed $map): ?array => \is_array($map) && [] !== $map ? $map : null,
            ));
            $this->addTextField('icon', [
                'label' => 'form.breadcrumb_item.icon.label',
                'placeholder' => false,
                'help' => false,
                'required' => false,
                'constraints' => [new Length(max: 128), BreadcrumbIconConstraint::create()],
            ]);
            $this->addWithDefaults($this->boundBuilder(), 'parent', EntityType::class, [
                'class' => BreadcrumbItem::class,
                'label' => 'form.breadcrumb_item.parent.label',
                'placeholder' => 'form.breadcrumb_item.parent.placeholder',
                'help' => false,
                'required' => false,
                // Item labels / route names, not translation keys.
                'choice_translation_domain' => false,
                'choice_label' => static function (BreadcrumbItem $item): string {
                    $l = $item->getLabel() ?: $item->getRouteName();

                    return $l.' (#'.(string) ($item->getId() ?? '?').')';
                },
                'query_builder' => static function (BreadcrumbItemRepository $repository) use ($collection, $excludeItem) {
                    $qb = $repository->createQueryBuilder('i')
                        ->andWhere('i.collection = :c')
                        ->setParameter('c', $collection)
                        ->orderBy('i.id', SortDirection::Ascending);
                    if ($excludeItem instanceof BreadcrumbItem && null !== $excludeItem->getId()) {
                        $qb->andWhere('i.id != :xid')->setParameter('xid', $excludeItem->getId());
                    }

                    return $qb;
                },
            ]);
        });

        $builder->get('staticRouteParams')->addModelTransformer(new JsonObjectTransformer());
        $builder->get('matchAttributes')->addModelTransformer(new JsonObjectTransformer());
        $builder->get('dynamicParamKeys')->addModelTransformer(new JsonStringListTransformer());
    }

    /**
     * Translations of locales that are not tabs are kept on save; the help of the field says so and lists them.
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var mixed $item null on a form created without data */
        $item = $form->getData();
        $map = $view->children['translations'] ?? null;
        if (!$item instanceof BreadcrumbItem || !$map instanceof FormView) {
            return;
        }
        /** @var list<string> $tabs */
        $tabs = $form->get('translations')->getConfig()->getOption('locales');
        $others = [];
        foreach ($item->getTranslations() ?? [] as $locale => $label) {
            if (!\in_array((string) $locale, $tabs, true) && '' !== $label) {
                $others[] = strtoupper((string) $locale).': '.$label;
            }
        }
        if ([] !== $others) {
            $map->vars['help'] = 'form.breadcrumb_item.translations.help_other_locales';
            $map->vars['help_translation_parameters'] = ['%labels%' => implode(' · ', $others)];
        }
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
