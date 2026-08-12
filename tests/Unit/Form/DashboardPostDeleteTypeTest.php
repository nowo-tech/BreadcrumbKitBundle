<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Form;

use Nowo\BreadcrumbKitBundle\Form\Dashboard\DashboardPostDeleteType;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DashboardPostDeleteTypeTest extends TestCase
{
    public function testConfigureOptionsSetsPostDefaults(): void
    {
        $resolver = new OptionsResolver();
        $type = new DashboardPostDeleteType();

        $type->configureOptions($resolver);
        $options = $resolver->resolve([
            'csrf_token_id' => 'delete_collection_42',
        ]);

        self::assertSame('POST', $options['method']);
        self::assertSame('form.delete.csrf_invalid', $options['csrf_message']);
        self::assertSame(NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN, $options['translation_domain']);
        self::assertSame('delete_collection_42', $options['csrf_token_id']);
    }
}
