<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Support;

use Nowo\BreadcrumbKitBundle\Dashboard\DashboardPages;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Form\BreadcrumbCollectionType;
use Nowo\BreadcrumbKitBundle\Form\Dashboard\DashboardGetSearchType;
use Nowo\BreadcrumbKitBundle\Form\Dashboard\DashboardPostDeleteType;
use Nowo\BreadcrumbKitBundle\Form\Dashboard\ImportBreadcrumbType;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validation;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Realistic dashboard page contexts, built like the controllers do (DashboardPages view models + real form types).
 * Used by the page contract test and the dashboard page tests.
 */
final class DashboardPageFixtures
{
    public const PREFIX = '/breadcrumb-kit-admin';

    /**
     * @return list<BreadcrumbCollection>
     */
    public static function collections(): array
    {
        return [
            self::collection(1, 'default', 'Main site', ''),
            self::collection(2, 'shop', null, 'store'),
            self::collection(3, 'admin', 'Back office', 'tenant-42'),
        ];
    }

    /**
     * @return list<BreadcrumbItem>
     */
    public static function items(BreadcrumbCollection $collection): array
    {
        $home = self::item(10, $collection, 'app_home', 'Home');
        $shop = self::item(11, $collection, 'app_shop', 'Shop', $home);
        $product = self::item(12, $collection, 'app_product_show', null, $shop, false);

        return [$home, $shop, $product];
    }

    public static function pages(?string $layout = null, ?Environment $twig = null): DashboardPages
    {
        return new DashboardPages(self::urls(), $twig ?? new Environment(new ArrayLoader()), $layout, [], ['collection_form' => 'xl']);
    }

    /**
     * @param list<BreadcrumbCollection> $collections
     *
     * @return array<string, mixed>
     */
    public static function collectionsIndex(array $collections, string $search = '', bool $paginated = true): array
    {
        $counts = [1 => 3, 2 => 12];

        return [
            'page' => self::pages()->collectionsIndex(
                $collections,
                $counts,
                self::searchForm(self::PREFIX.'/collections', $search),
                $search,
                DashboardPages::pagination($paginated, 1, 2, \count($collections), DashboardPages::ROUTE_COLLECTIONS, '' !== $search ? ['q' => $search] : []),
                static fn (int $id): FormView => self::deleteForm('delete_collection_'.$id),
            ),
            'import_form' => self::importForm(),
            'nowo_breadcrumb_kit_dialog_sizes' => ['collection_form' => 'xl', 'item_form' => 'lg', 'import' => 'md'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function itemsIndex(BreadcrumbCollection $collection, bool $empty = false, string $search = ''): array
    {
        $items = $empty ? [] : self::items($collection);

        return [
            'page' => self::pages()->itemsIndex(
                $collection,
                $items,
                self::searchForm(self::PREFIX.'/collections/'.$collection->getId().'/items', $search),
                $search,
                DashboardPages::pagination(true, 1, 20, \count($items), DashboardPages::ROUTE_ITEMS, [], ['collectionId' => $collection->getId()]),
                static fn (int $id): FormView => self::deleteForm('delete_item_'.$id),
            ),
            'collection' => $collection,
            'nowo_breadcrumb_kit_dialog_sizes' => ['collection_form' => 'lg', 'item_form' => 'lg', 'import' => 'md'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function collectionForm(?BreadcrumbCollection $collection, bool $withError = false): array
    {
        $form = self::factory()->create(BreadcrumbCollectionType::class, $collection ?? new BreadcrumbCollection(), ['inline_edit_access_keys' => ['demo_public']]);
        if ($withError) {
            $form->addError(new FormError('A collection with this code and context key already exists.'));
        }
        $id = $collection?->getId();

        return [
            'page' => self::pages()->collectionForm($form->createView(), $collection, null !== $id ? self::deleteForm('delete_collection_'.$id) : null),
            'collection' => $collection,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function itemForm(BreadcrumbCollection $collection, ?BreadcrumbItem $item): array
    {
        $id = $item?->getId();

        return [
            'page' => self::pages()->itemForm(self::itemFormView($item), $collection, $item, null !== $id ? self::deleteForm('delete_item_'.$id) : null),
            'collection' => $collection,
            'item' => $item,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function collectionDelete(BreadcrumbCollection $collection): array
    {
        return ['page' => self::pages()->collectionDelete($collection, self::deleteForm('delete_collection_'.$collection->getId())), 'collection' => $collection];
    }

    /**
     * @return array<string, mixed>
     */
    public static function itemDelete(BreadcrumbCollection $collection, BreadcrumbItem $item): array
    {
        return ['page' => self::pages()->itemDelete($collection, $item, self::deleteForm('delete_item_'.$item->getId())), 'collection' => $collection, 'item' => $item];
    }

    /**
     * @return array<string, mixed>
     */
    public static function import(): array
    {
        return ['page' => self::pages()->import(self::importForm())];
    }

    public static function importForm(): FormView
    {
        return self::factory()->create(ImportBreadcrumbType::class, null, ['action' => self::PREFIX.'/import'])->createView();
    }

    /**
     * The item form with the field names, labels and help of BreadcrumbItemType (its `parent` EntityType needs
     * Doctrine; here a choice field with the same label / placeholder).
     */
    public static function itemFormView(?BreadcrumbItem $item): FormView
    {
        $field = static fn (string $key, bool $help = false): array => ['label' => 'form.breadcrumb_item.'.$key.'.label', 'required' => false] + ($help ? ['help' => 'form.breadcrumb_item.'.$key.'.help'] : []);

        return self::factory()->createNamedBuilder('breadcrumb_item', FormType::class, null, ['translation_domain' => NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN])
            ->add('routeName', TextType::class, ['label' => 'form.breadcrumb_item.route_name.label', 'data' => $item?->getRouteName(), 'attr' => ['placeholder' => 'app_product_show']])
            ->add('staticRouteParams', TextareaType::class, $field('static_params', true) + ['attr' => ['rows' => 3, 'class' => 'nowo-ui-code']])
            ->add('pathPattern', TextType::class, $field('path_pattern', true) + ['attr' => ['placeholder' => '^/shop/.+', 'class' => 'nowo-ui-code']])
            ->add('matchAttributes', TextareaType::class, $field('match_attributes', true) + ['attr' => ['rows' => 2, 'class' => 'nowo-ui-code']])
            ->add('dynamicParamKeys', TextareaType::class, $field('dynamic_keys', true) + ['attr' => ['rows' => 2, 'class' => 'nowo-ui-code']])
            ->add('linkEnabled', CheckboxType::class, $field('link_enabled') + ['data' => $item?->isLinkEnabled() ?? true])
            ->add('label', TextType::class, $field('label') + ['data' => $item?->getLabel()])
            ->add('translations', TextareaType::class, $field('translations', true) + ['attr' => ['rows' => 4, 'class' => 'nowo-ui-code']])
            ->add('icon', TextType::class, $field('icon'))
            ->add('parent', ChoiceType::class, $field('parent') + ['placeholder' => 'form.breadcrumb_item.parent.placeholder', 'choices' => ['Home (#10)' => 10, 'Shop (#11)' => 11]])
            ->getForm()
            ->createView();
    }

    public static function searchForm(string $action, string $search = ''): FormView
    {
        return self::factory()->create(DashboardGetSearchType::class, ['q' => $search], [
            'action' => $action,
            'search_placeholder' => 'Search by code, name or context…',
            'search_label' => 'Search',
        ])->createView();
    }

    public static function deleteForm(string $tokenId): FormView
    {
        return self::factory()->createNamed('', DashboardPostDeleteType::class, null, ['csrf_token_id' => $tokenId])->createView();
    }

    public static function urls(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            private RequestContext $context;

            public function __construct()
            {
                $this->context = new RequestContext();
            }

            public function setContext(RequestContext $context): void
            {
                $this->context = $context;
            }

            public function getContext(): RequestContext
            {
                return $this->context;
            }

            /**
             * @param array<string, mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                $path = match ($name) {
                    DashboardPages::ROUTE_COLLECTIONS => '/collections',
                    DashboardPages::ROUTE_COLLECTION_NEW => '/collections/new',
                    DashboardPages::ROUTE_COLLECTION_EDIT => '/collections/{id}/edit',
                    DashboardPages::ROUTE_COLLECTION_DELETE => '/collections/{id}/delete',
                    DashboardPages::ROUTE_COLLECTION_EXPORT => '/collections/{id}/export',
                    DashboardPages::ROUTE_ITEMS => '/collections/{collectionId}/items',
                    DashboardPages::ROUTE_ITEM_NEW => '/collections/{collectionId}/items/new',
                    DashboardPages::ROUTE_ITEM_EDIT => '/collections/{collectionId}/items/{id}/edit',
                    DashboardPages::ROUTE_ITEM_DELETE => '/collections/{collectionId}/items/{id}/delete',
                    DashboardPages::ROUTE_IMPORT => '/import',
                    DashboardPages::ROUTE_EXPORT_ALL => '/export',
                    default => '/'.$name,
                };
                foreach ($parameters as $key => $value) {
                    if (str_contains($path, '{'.$key.'}')) {
                        $path = str_replace('{'.$key.'}', (string) $value, $path);
                        unset($parameters[$key]);
                    }
                }

                return DashboardPageFixtures::PREFIX.$path.([] === $parameters ? '' : '?'.http_build_query($parameters));
            }
        };
    }

    public static function factory(): FormFactoryInterface
    {
        $profile = ['translation_domain' => NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN, 'defaults' => ['attr' => [], 'row_attr' => []], 'field_types' => []];
        $merger = new FormOptionsMerger(['breadcrumb_kit' => $profile, 'default' => $profile], 'breadcrumb_kit', new ConstraintDefinitionFactory());
        $types = [new BreadcrumbCollectionType(), new DashboardGetSearchType(), new ImportBreadcrumbType()];
        foreach ($types as $type) {
            $type->setFormOptionsMerger($merger);
        }

        $builder = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addExtension(new CsrfExtension(new class implements CsrfTokenManagerInterface {
                public function getToken(string $tokenId): CsrfToken
                {
                    return new CsrfToken($tokenId, 'token-'.$tokenId);
                }

                public function refreshToken(string $tokenId): CsrfToken
                {
                    return $this->getToken($tokenId);
                }

                public function removeToken(string $tokenId): ?string
                {
                    return null;
                }

                public function isTokenValid(CsrfToken $token): bool
                {
                    return true;
                }
            }))
            ->addType(new DashboardPostDeleteType());
        foreach ($types as $type) {
            $builder->addType($type);
        }

        return $builder->getFormFactory();
    }

    public static function collection(int $id, string $code, ?string $name, string $contextKey): BreadcrumbCollection
    {
        $collection = (new BreadcrumbCollection())->setCode($code)->setName($name)->setContextKey($contextKey);
        (new \ReflectionProperty(BreadcrumbCollection::class, 'id'))->setValue($collection, $id);

        return $collection;
    }

    public static function item(int $id, BreadcrumbCollection $collection, string $route, ?string $label, ?BreadcrumbItem $parent = null, bool $link = true): BreadcrumbItem
    {
        $item = new BreadcrumbItem();
        $item->setCollection($collection);
        $item->setRouteName($route);
        $item->setLabel($label);
        $item->setParent($parent);
        $item->setLinkEnabled($link);
        (new \ReflectionProperty(BreadcrumbItem::class, 'id'))->setValue($item, $id);

        return $item;
    }
}
