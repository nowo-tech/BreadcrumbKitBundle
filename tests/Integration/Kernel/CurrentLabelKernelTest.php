<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCurrentLabel;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCurrentLabelInterface;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

/**
 * 2.17: one booted kernel serves many requests with no kernel reset (FrankenPHP worker mode, `kernel.reset` never
 * called): the current-crumb label of one request never reaches the next one.
 */
final class CurrentLabelKernelTest extends TestCase
{
    private ?CurrentLabelTestKernel $kernel = null;

    protected function setUp(): void
    {
        (new Filesystem())->remove(CurrentLabelTestKernel::baseDir());
        $this->kernel = new CurrentLabelTestKernel();
        $this->kernel->boot();

        $em = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(BreadcrumbCollection::class), $em->getClassMetadata(BreadcrumbItem::class)]);

        $importer = $this->service(BreadcrumbImporter::class);
        self::assertInstanceOf(BreadcrumbImporter::class, $importer);
        $result = $importer->import(['collection' => ['code' => 'default'], 'items' => [
            ['routeName' => 'home', 'label' => 'Home', 'children' => [
                ['routeName' => 'wiki_php', 'label' => 'Page', 'linkEnabled' => false],
                ['routeName' => 'wiki_twig', 'label' => 'Page', 'linkEnabled' => false],
                ['routeName' => 'wiki_fail', 'label' => 'Page', 'linkEnabled' => false],
                ['routeName' => 'wiki_frag', 'label' => 'Page', 'linkEnabled' => false],
            ]],
        ]]);
        self::assertSame([], $result['errors']);
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        (new Filesystem())->remove(CurrentLabelTestKernel::baseDir());
    }

    public function testManyRequestsOnOneKernelNeverShareTheLabel(): void
    {
        self::assertSame(['Home', 'PHP alpha'], $this->crumbs('/wiki/php/alpha'));
        self::assertTrue($this->isClear(), 'terminate cleared it');

        self::assertSame(['Home', 'Page'], $this->crumbs('/wiki/php/none'));
        self::assertSame(['Home', 'Twig beta'], $this->crumbs('/wiki/twig/beta'));
        self::assertSame(['Home', 'Page'], $this->crumbs('/wiki/php/none'));

        $this->crumbs('/wiki/fail', 500);
        self::assertSame(['Home', 'Page'], $this->crumbs('/wiki/php/none'));

        self::assertSame(['Home', 'PHP gamma'], $this->crumbs('/wiki/php/gamma'));
        self::assertSame(['Home'], $this->crumbs('/'));
    }

    public function testLabelLeftWithoutTerminateIsClearedWhenTheNextRequestStarts(): void
    {
        $kernel = $this->kernel;
        self::assertNotNull($kernel);

        // A worker that never reached terminate (fatal in a listener, early exit).
        $kernel->handle(Request::create('/wiki/php/alpha'));
        self::assertFalse($this->isClear());

        $html = (string) $kernel->handle(Request::create('/wiki/php/none'))->getContent();
        self::assertStringNotContainsString('PHP alpha', $html);
        self::assertStringContainsString('Page', $html);
    }

    public function testSubRequestFragmentKeepsItsOwnLabel(): void
    {
        $kernel = $this->kernel;
        self::assertNotNull($kernel);
        $request = Request::create('/wiki/frag/delta');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $html = (string) $response->getContent();
        self::assertSame(200, $response->getStatusCode(), $html);

        [$main, $fragment] = explode('<div id="fragment">', $html, 2);
        self::assertSame(['Home', 'Main delta'], $this->parse($main));
        self::assertSame(['Home', 'Page'], $this->parse($fragment));
        self::assertTrue($this->isClear());
    }

    public function testTwigLabelIsEscaped(): void
    {
        $kernel = $this->kernel;
        self::assertNotNull($kernel);

        $html = (string) $kernel->handle(Request::create('/wiki/twig/'.rawurlencode('<img src=x onerror=alert(1)>')))->getContent();

        self::assertStringContainsString('Twig &lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringNotContainsString('<img', $html);
    }

    public function testConsoleCommandBoundariesClearTheLabel(): void
    {
        $kernel = $this->kernel;
        self::assertNotNull($kernel);
        $label = $this->service(BreadcrumbCurrentLabelInterface::class);
        self::assertInstanceOf(BreadcrumbCurrentLabel::class, $label);

        // A label left over by an earlier request on this worker (no request is running now, so seed it directly).
        self::seed($label);
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $command = new class($label) extends Command {
            public ?bool $clearedAtStart = null;

            public function __construct(private readonly BreadcrumbCurrentLabel $label)
            {
                parent::__construct('app:current-label');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $this->clearedAtStart = null === (new ReflectionProperty(BreadcrumbCurrentLabel::class, 'pending'))->getValue($this->label);
                CurrentLabelKernelTest::seed($this->label);

                return Command::SUCCESS;
            }
        };
        $application->addCommand($command);

        self::assertSame(0, $application->run(new ArrayInput(['command' => 'app:current-label']), new BufferedOutput()));
        self::assertTrue($command->clearedAtStart, 'command start cleared it');
        self::assertTrue($this->isClear(), 'command end cleared it');
    }

    /**
     * @return list<string>
     */
    private function crumbs(string $uri, int $status = 200): array
    {
        $kernel = $this->kernel;
        self::assertNotNull($kernel);
        $request = Request::create($uri);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());

        return $this->parse((string) $response->getContent());
    }

    /**
     * @return list<string>
     */
    private function parse(string $html): array
    {
        preg_match_all('#<li class="breadcrumb-item[^"]*">\s*(?:<span[^>]*>)?\s*(.*?)\s*(?:</span>|</a>|</li>)#s', $html, $m);

        return array_map(static fn (string $s): string => trim(html_entity_decode(strip_tags($s))), $m[1]);
    }

    public static function seed(BreadcrumbCurrentLabel $label): void
    {
        (new ReflectionProperty(BreadcrumbCurrentLabel::class, 'pending'))->setValue($label, [
            'label' => 'Left over',
            'domain' => null,
            'parameters' => [],
            'route' => 'wiki_php',
            'routeParams' => [],
            'collection' => null,
            'contextKey' => null,
        ]);
    }

    private function isClear(): bool
    {
        $label = $this->service(BreadcrumbCurrentLabelInterface::class);

        return null === (new ReflectionProperty(BreadcrumbCurrentLabel::class, 'pending'))->getValue($label);
    }

    private function service(string $id): object
    {
        self::assertNotNull($this->kernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container->get($id);
    }
}
