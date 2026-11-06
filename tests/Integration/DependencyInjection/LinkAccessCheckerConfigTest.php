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

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidCheckers(): iterable
    {
        yield 'integer' => [5];
        yield 'false' => [false];
        yield 'empty' => [''];
        yield 'blank' => ['   '];
    }

    #[DataProvider('invalidCheckers')]
    public function testInvalidValueIsRefused(mixed $value): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('link_access_checker');

        (new Processor())->processConfiguration(new Configuration(), [['link_access_checker' => $value]]);
    }

    public function testParamMapAttributesReachTheLoader(): void
    {
        self::assertSame([], $this->loaderArgument([], '$paramMapAttributes'));
        self::assertSame(['projectId'], $this->loaderArgument(['param_map' => ['attributes' => ['projectId']]], '$paramMapAttributes'));
    }

    public function testReservedAttributeNameIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('param_map.attributes');

        (new Processor())->processConfiguration(new Configuration(), [['param_map' => ['attributes' => ['_controller']]]]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function loaderArgument(array $config, string $argument = '$linkAccessChecker'): mixed
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        (new BreadcrumbKitExtension())->load([$config], $container);

        return $container->getDefinition(BreadcrumbLoader::class)->getArgument($argument);
    }
}
