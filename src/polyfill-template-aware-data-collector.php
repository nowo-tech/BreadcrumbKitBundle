<?php

declare(strict_types=1);
use Symfony\Bundle\FrameworkBundle\DataCollector\TemplateAwareDataCollectorInterface;

/*
 * Symfony 7.2+: TemplateAwareDataCollectorInterface was moved from HttpKernel to FrameworkBundle.
 * {@see BreadcrumbDataCollector} still implements the legacy FQCN for Symfony 6.4 compatibility.
 */
if (
    interface_exists(TemplateAwareDataCollectorInterface::class)
    && !interface_exists(Symfony\Component\HttpKernel\DataCollector\TemplateAwareDataCollectorInterface::class, false)
) {
    class_alias(
        TemplateAwareDataCollectorInterface::class,
        Symfony\Component\HttpKernel\DataCollector\TemplateAwareDataCollectorInterface::class,
    );
}
