<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Form\BreadcrumbItemType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * 2.8: the item label translations are a FormKit TranslatableMapType (one tab per locale) instead of a JSON textarea;
 * real kernel, so the locales reach the form type through the extension (literal list, parameter, env var).
 */
final class ItemFormLocaleTabsKernelTest extends TestCase
{
    private ?LocaleParameterTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        (new Filesystem())->remove(sys_get_temp_dir().'/nowo-breadcrumb-kit-locale-kernel');
    }

    public function testTabsFollowTheConfiguredLocalesAndUnknownLocalesAreKept(): void
    {
        $item = $this->boot(['locales' => ['en', 'es', 'fr'], 'default_locale' => 'es'], ['es' => 'Inicio', 'de' => 'Startseite']);
        $form = $this->form($item);

        $map = $form->get('translations');
        self::assertSame(['en', 'es', 'fr'], array_keys($map->all()));
        self::assertSame('es', $map->getConfig()->getOption('default_locale'));
        self::assertSame('Inicio', $map->get('es')->getData());

        $view = $form->createView();
        self::assertSame('form.breadcrumb_item.translations.help_other_locales', $view['translations']->vars['help']);
        self::assertSame(['%labels%' => 'DE: Startseite'], $view['translations']->vars['help_translation_parameters']);
        self::assertSame('es', $view['translations']->vars['locale_tabs']['default_locale']);

        $form->submit($this->payload(['en' => 'Home', 'es' => '', 'fr' => 'Accueil']));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        $translations = $item->getTranslations();
        self::assertIsArray($translations);
        ksort($translations);
        self::assertSame(['de' => 'Startseite', 'en' => 'Home', 'fr' => 'Accueil'], $translations, 'emptied tab removed, unknown locale kept');
    }

    public function testNoTranslationsIsStoredAsNullAndNoNoticeIsShown(): void
    {
        $item = $this->boot(['locales' => ['en', 'es']], null);
        $form = $this->form($item);

        self::assertSame('form.breadcrumb_item.translations.help', $form->createView()['translations']->vars['help']);
        $form->submit($this->payload(['en' => '', 'es' => '']));

        self::assertTrue($form->isValid());
        self::assertNull($item->getTranslations());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>, string}>
     */
    public static function localeConfigs(): iterable
    {
        yield 'no bundle locales: framework.enabled_locales and kernel.default_locale through FormKit' => [[], ['de', 'en'], 'en'];
        yield 'parameter' => [['locales' => '%app.locales%'], ['es', 'en', 'fr'], 'es'];
        yield 'env var' => [['locales' => '%env(json:'.LocaleEnvVarLoader::JSON.')%'], ['pt', 'en'], 'pt'];
        yield 'default outside the list' => [['locales' => ['en', 'es'], 'default_locale' => 'pt'], ['en', 'es'], 'en'];
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string>         $tabs
     */
    #[DataProvider('localeConfigs')]
    public function testTabsComeFromTheBundleLocales(array $config, array $tabs, string $default): void
    {
        $form = $this->form($this->boot($config, null));

        self::assertSame($tabs, array_keys($form->get('translations')->all()));
        self::assertSame($default, $form->get('translations')->getConfig()->getOption('default_locale'));
    }

    public function testTooLongLabelIsAnErrorOnItsLocaleTab(): void
    {
        $form = $this->form($this->boot(['locales' => ['en', 'es']], null));
        $form->submit($this->payload(['en' => 'Home', 'es' => str_repeat('x', 513)]));

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('translations')->get('es')->getErrors());
        self::assertFalse($form->createView()['translations']['es']->vars['valid']);
    }

    /**
     * @param array<string, mixed>       $config
     * @param array<string, string>|null $translations
     */
    private function boot(array $config, ?array $translations): BreadcrumbItem
    {
        $this->kernel = new LocaleParameterTestKernel('form-'.md5(serialize($config)), $config);
        $this->kernel->boot();
        $em = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(BreadcrumbCollection::class), $em->getClassMetadata(BreadcrumbItem::class)]);

        $collection = (new BreadcrumbCollection())->setCode('main');
        $item = (new BreadcrumbItem())->setCollection($collection)->setRouteName('app_home')->setTranslations($translations);
        $em->persist($collection);
        $em->persist($item);
        $em->flush();

        return $item;
    }

    /**
     * @return FormInterface<BreadcrumbItem>
     */
    private function form(BreadcrumbItem $item): FormInterface
    {
        $factory = $this->service('form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        return $factory->create(BreadcrumbItemType::class, $item, ['collection' => $item->getCollection()]);
    }

    private function service(string $id): object
    {
        self::assertNotNull($this->kernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $service = $container->get($id);

        return $service;
    }

    /**
     * @param array<string, string> $translations
     *
     * @return array<string, mixed>
     */
    private function payload(array $translations): array
    {
        return ['routeName' => 'app_home', 'linkEnabled' => '1', 'translations' => $translations];
    }
}
