<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;

/**
 * Seeds collections and items that exercise bundle features (see docs/DEMO-FRANKENPHP.md).
 */
final class BreadcrumbDemoFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $manager->persist($this->createDefaultCollection());
        $manager->persist($this->createAdminCollection());
        $manager->flush();
    }

    private function createDefaultCollection(): BreadcrumbCollection
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode('default');
        $collection->setContextKey('');
        $collection->setName('Demo (public)');
        $collection->setHomeIcon('🏠');
        $collection->setSeparatorIcon('›');
        $collection->setClassList('breadcrumb breadcrumb--demo');
        $collection->setClassItem('breadcrumb-demo-item');
        $collection->setClassSeparator('breadcrumb-demo-sep');
        $collection->setClassCurrent('breadcrumb-demo-current');
        $collection->setResponsiveConfig([
            'breakpoint' => 768,
            'many_items_hint' => 'horizontal_scroll_below_breakpoint',
            // GitHub-style trail below 40rem: "…" menu + current crumb (try /demo/deep/… at phone width).
            'collapse' => ['enabled' => true, 'min_items' => 4, 'breakpoint' => '40rem'],
        ]);
        $collection->setInlineEditAccessKey('demo_public');

        $home = new BreadcrumbItem();
        $home->setRouteName('app_home');
        $home->setStaticRouteParams([]);
        $home->setLabel('Home');
        $home->setTranslations(['en' => 'Home', 'es' => 'Inicio']);
        $home->setLinkEnabled(true);
        $home->setIcon('⌂');
        $collection->addItem($home);

        $shop = new BreadcrumbItem();
        $shop->setRouteName('app_shop');
        $shop->setStaticRouteParams([]);
        $shop->setLabel('Shop');
        $shop->setTranslations(['en' => 'Shop', 'es' => 'Tienda']);
        $shop->setLinkEnabled(false);
        $shop->setParent($home);
        $collection->addItem($shop);

        $product = new BreadcrumbItem();
        $product->setRouteName('app_product_show');
        $product->setStaticRouteParams([]);
        $product->setLabel('Product');
        $product->setTranslations(['en' => 'Product', 'es' => 'Producto']);
        $product->setLinkEnabled(true);
        $product->setParent($shop);
        $product->setDynamicParamKeys(['id']);
        $collection->addItem($product);

        $sales = new BreadcrumbItem();
        $sales->setRouteName('app_section_show');
        $sales->setStaticRouteParams(['section' => 'sales']);
        $sales->setLabel('Sales');
        $sales->setTranslations(['en' => 'Sales', 'es' => 'Ventas']);
        $sales->setLinkEnabled(true);
        $sales->setParent($home);
        $collection->addItem($sales);

        $support = new BreadcrumbItem();
        $support->setRouteName('app_section_show');
        $support->setStaticRouteParams(['section' => 'support']);
        $support->setLabel('Support');
        $support->setTranslations(['en' => 'Support', 'es' => 'Soporte']);
        $support->setLinkEnabled(true);
        $support->setParent($home);
        $collection->addItem($support);

        $trailDemo = new BreadcrumbItem();
        $trailDemo->setRouteName('app_demo_breadcrumb_trail');
        $trailDemo->setStaticRouteParams([]);
        $trailDemo->setLabel('breadcrumb_trail()');
        $trailDemo->setTranslations(['en' => 'breadcrumb_trail()', 'es' => 'breadcrumb_trail()']);
        $trailDemo->setLinkEnabled(true);
        $trailDemo->setParent($home);
        $collection->addItem($trailDemo);

        $customTpl = new BreadcrumbItem();
        $customTpl->setRouteName('app_demo_custom_template');
        $customTpl->setStaticRouteParams([]);
        $customTpl->setLabel('Custom template');
        $customTpl->setTranslations(['en' => 'Custom template', 'es' => 'Plantilla personalizada']);
        $customTpl->setLinkEnabled(true);
        $customTpl->setParent($home);
        $collection->addItem($customTpl);

        $deepHub = new BreadcrumbItem();
        $deepHub->setRouteName('app_demo_deep');
        $deepHub->setStaticRouteParams([]);
        $deepHub->setLabel('Deep trail');
        $deepHub->setTranslations(['en' => 'Deep trail', 'es' => 'Cadena profunda']);
        $deepHub->setLinkEnabled(true);
        $deepHub->setParent($home);
        $collection->addItem($deepHub);

        // Dashboard pages (UiKit page headers render this trail: nowo_ui_kit.breadcrumbs.breadcrumb_kit).
        $admin = new BreadcrumbItem();
        $admin->setRouteName('nowo_breadcrumb_kit_dashboard_collections_index');
        $admin->setStaticRouteParams([]);
        $admin->setLabel('Breadcrumb collections');
        $admin->setTranslations(['en' => 'Breadcrumb collections', 'es' => 'Colecciones de migas']);
        $admin->setLinkEnabled(true);
        $admin->setParent($home);
        $collection->addItem($admin);

        foreach ([
            ['nowo_breadcrumb_kit_dashboard_items_index', 'Items', 'Ítems', ['collectionId']],
            ['nowo_breadcrumb_kit_dashboard_import', 'Import', 'Importar', null],
            ['nowo_breadcrumb_kit_dashboard_collections_new', 'New collection', 'Nueva colección', null],
            ['nowo_breadcrumb_kit_dashboard_collections_edit', 'Edit collection', 'Editar colección', ['id']],
        ] as [$route, $en, $es, $keys]) {
            $page = new BreadcrumbItem();
            $page->setRouteName($route);
            $page->setStaticRouteParams([]);
            $page->setLabel($en);
            $page->setTranslations(['en' => $en, 'es' => $es]);
            $page->setLinkEnabled(true);
            $page->setParent($admin);
            if (null !== $keys) {
                $page->setDynamicParamKeys($keys);
            }
            $collection->addItem($page);
        }

        $deepPrev = $deepHub;
        for ($i = 1; $i <= 6; ++$i) {
            $step = new BreadcrumbItem();
            $step->setRouteName('app_demo_deep_s'.$i);
            $step->setStaticRouteParams([]);
            $step->setLabel('Step '.$i);
            $step->setTranslations([
                'en' => 'Segment '.$i,
                'es' => 'Tramo '.$i,
            ]);
            $step->setLinkEnabled(true);
            $step->setParent($deepPrev);
            $collection->addItem($step);
            $deepPrev = $step;
        }

        return $collection;
    }

    private function createAdminCollection(): BreadcrumbCollection
    {
        $collection = new BreadcrumbCollection();
        $collection->setCode('admin');
        $collection->setContextKey('');
        $collection->setName('Demo (admin)');
        $collection->setSeparatorIcon('·');
        $collection->setClassList('breadcrumb breadcrumb--admin');
        $collection->setResponsiveConfig(['breakpoint' => 768]);
        $collection->setInlineEditAccessKey('demo_staff_only');

        $root = new BreadcrumbItem();
        $root->setRouteName('app_admin_dashboard');
        $root->setStaticRouteParams([]);
        $root->setLabel('Admin');
        $root->setTranslations(['en' => 'Admin', 'es' => 'Administración']);
        $root->setLinkEnabled(true);
        $collection->addItem($root);

        $users = new BreadcrumbItem();
        $users->setRouteName('app_admin_users');
        $users->setStaticRouteParams([]);
        $users->setLabel('Users');
        $users->setTranslations(['en' => 'Users', 'es' => 'Usuarios']);
        $users->setLinkEnabled(true);
        $users->setParent($root);
        $collection->addItem($users);

        $longPrev = $root;
        for ($w = 1; $w <= 5; ++$w) {
            $node = new BreadcrumbItem();
            $node->setRouteName('app_admin_long_w'.$w);
            $node->setStaticRouteParams([]);
            $node->setLabel('Workspace '.$w);
            $node->setTranslations([
                'en' => 'Workspace '.$w,
                'es' => 'Espacio '.$w,
            ]);
            $node->setLinkEnabled(true);
            $node->setParent($longPrev);
            $collection->addItem($node);
            $longPrev = $node;
        }

        return $collection;
    }
}
