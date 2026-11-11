<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Stringable;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Write port for the page being rendered to name the current (last) crumb of its trail (2.17).
 *
 * The label is scoped to the request being handled when it is set: it replaces the label of the matched item only in a
 * trail built for that route (same scalar route parameters) during that main request; fragments, other routes and
 * generic (`*`) items keep theirs. Nothing is stored. Plain text: the bundle templates escape it. The read side is
 * {@see BreadcrumbCurrentLabelProviderInterface}.
 */
interface BreadcrumbCurrentLabelInterface
{
    /**
     * @param string|Stringable|TranslatableInterface|null $label                 null or blank: back to the item's own label
     * @param string|null                                  $translationDomain     when set, the string label is a message key
     *                                                                            translated in that domain (request locale)
     * @param array<string, mixed>                         $translationParameters parameters of that message
     * @param string|null                                  $collection            only trails of this collection code (null: any)
     * @param string|null                                  $contextKey            only trails of this context key (null: any)
     */
    public function setCurrentLabel(
        string|Stringable|TranslatableInterface|null $label,
        ?string $translationDomain = null,
        array $translationParameters = [],
        ?string $collection = null,
        ?string $contextKey = null,
    ): void;

    /**
     * Forgets the label: the matched item's label is shown again.
     */
    public function clearCurrentLabel(): void;
}
