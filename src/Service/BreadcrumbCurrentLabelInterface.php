<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Stringable;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Port for the page being rendered to name the current (last) crumb of the trail (2.17).
 *
 * The label replaces the static label of the matched item for the current main request only; nothing is stored.
 * A controller, an application service (type-hint this interface) or the template (`breadcrumb_current_label()`)
 * sets it before the trail is built. Plain text: the bundle templates escape it.
 */
interface BreadcrumbCurrentLabelInterface
{
    /**
     * @param string|Stringable|TranslatableInterface|null $label                 null or blank: back to the item's own label
     * @param string|null                                  $translationDomain     when set, the string label is a message key
     *                                                                            translated in that domain (request locale)
     * @param array<string, mixed>                         $translationParameters parameters of that message
     */
    public function setCurrentLabel(string|Stringable|TranslatableInterface|null $label, ?string $translationDomain = null, array $translationParameters = []): void;

    /**
     * Forgets the label of the current request: the matched item's label is shown again.
     */
    public function clearCurrentLabel(): void;
}
