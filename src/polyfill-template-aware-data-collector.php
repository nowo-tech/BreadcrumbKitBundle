<?php

declare(strict_types=1);

/**
 * Symfony 7.2+: TemplateAwareDataCollectorInterface was moved from HttpKernel to FrameworkBundle.
 * {@see BreadcrumbDataCollector} still implements the legacy FQCN for Symfony 6.4 compatibility.
 */
if (
    interface_exists(Symfony\Bundle\FrameworkBundle\DataCollector\TemplateAwareDataCollectorInterface::class)
    && !interface_exists(Symfony\Component\HttpKernel\DataCollector\TemplateAwareDataCollectorInterface::class, false)
) {
    class_alias(
        Symfony\Bundle\FrameworkBundle\DataCollector\TemplateAwareDataCollectorInterface::class,
        Symfony\Component\HttpKernel\DataCollector\TemplateAwareDataCollectorInterface::class,
    );
}
