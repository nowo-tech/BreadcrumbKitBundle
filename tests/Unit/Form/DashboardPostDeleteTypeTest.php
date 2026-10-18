<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Form;

use Nowo\BreadcrumbKitBundle\Form\Dashboard\DashboardPostDeleteType;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

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

    public function testUnnamedDeleteFormIsSubmittedByAPostWithTheToken(): void
    {
        $tokens = new CsrfTokenManager();
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new CsrfExtension($tokens))
            ->getFormFactory();
        $build = static fn () => $factory->createNamedBuilder('', DashboardPostDeleteType::class, null, ['csrf_token_id' => 'delete_item_7'])->getForm();

        $view = $build()->createView();
        self::assertSame(['confirm', '_token'], array_keys($view->children));

        $form = $build();
        $form->handleRequest(Request::create('/delete/7', 'POST', ['confirm' => '1', '_token' => $tokens->getToken('delete_item_7')->getValue()]));
        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isValid());

        $forged = $build();
        $forged->handleRequest(Request::create('/delete/7', 'POST', ['confirm' => '1', '_token' => 'forged']));
        self::assertTrue($forged->isSubmitted());
        self::assertFalse($forged->isValid());
    }
}
