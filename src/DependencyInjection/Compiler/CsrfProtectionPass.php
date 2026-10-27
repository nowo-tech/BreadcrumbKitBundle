<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\DependencyInjection\Compiler;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails closed when the dashboard is enabled without form CSRF protection (REQ-SEC-005).
 *
 * The mutations of the dashboard rely on Symfony's form CSRF extension: forms that declare their own `csrf_protection` /
 * `csrf_token_id` options would otherwise validate without a token when `form.type_extension.csrf` is missing or
 * `framework.form.csrf_protection` is false. The container refuses to compile instead.
 */
final class CsrfProtectionPass implements CompilerPassInterface
{
    /** Parameter whose value is true when the dashboard is registered. */
    public const ENABLED_PARAMETER = 'nowo_breadcrumb_kit.web_ui.enabled';

    /**
     * Throws when the dashboard is enabled and form CSRF protection or the CSRF token manager is unavailable.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::ENABLED_PARAMETER) || true !== $container->getParameter(self::ENABLED_PARAMETER)) {
            return;
        }

        $formCsrfDisabled = $container->hasParameter('form.type_extension.csrf.enabled')
            && false === $container->getParameter('form.type_extension.csrf.enabled');

        if (
            !$container->has('security.csrf.token_manager')
            || !$container->hasDefinition('form.type_extension.csrf')
            || $formCsrfDisabled
        ) {
            throw new LogicException('NowoBreadcrumbKitBundle: the dashboard (web_ui.enabled: true) requires CSRF protection. Enable framework.csrf_protection and framework.form.csrf_protection (composer require symfony/security-csrf), or set nowo_breadcrumb_kit.web_ui.enabled: false.');
        }
    }
}
