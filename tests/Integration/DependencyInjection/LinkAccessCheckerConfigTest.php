<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Integration\DependencyInjection;

use Nowo\BreadcrumbKitBundle\DependencyInjection\BreadcrumbKitExtension;
use Nowo\BreadcrumbKitBundle\DependencyInjection\Configuration;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class LinkAccessCheckerConfigTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unset(): iterable
    {
        yield 'not configured' => [[]];
        yield 'null' => [['link_access_checker' => null]];
        yield 'empty' => [['link_access_checker' => ' ']];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('unset')]
    public function testUnsetInjectsNothing(array $config): void
    {
        self::assertNull($this->loaderArgument($config));
    }

    public function testServiceIdIsReferenced(): void
    {
        $argument = $this->loaderArgument(['link_access_checker' => 'app.link_checker']);

        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame('app.link_checker', (string) $argument);
    }

    public function testNonStringIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('link_access_checker');

        (new Processor())->processConfiguration(new Configuration(), [['link_access_checker' => 5]]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function loaderArgument(array $config): mixed
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        (new BreadcrumbKitExtension())->load([$config], $container);

        return $container->getDefinition(BreadcrumbLoader::class)->getArgument('$linkAccessChecker');
    }
}
