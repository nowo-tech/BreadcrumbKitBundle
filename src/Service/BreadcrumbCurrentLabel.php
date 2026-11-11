<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Stringable;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;
use WeakReference;

/**
 * Request-scoped label of the current crumb (2.17), read by {@see BreadcrumbLoader} when it builds a trail.
 *
 * Worker safety (FrankenPHP, kernel reset false): the label is dropped when a main request starts, ends or fails, when a
 * console command starts or ends and when a Messenger worker receives or finishes a message; on top of that it only
 * applies while the main request it was set for is still the current one. `reset()` clears it too, but nothing relies on
 * kernel.reset.
 */
final class BreadcrumbCurrentLabel implements BreadcrumbCurrentLabelInterface, EventSubscriberInterface, ResetInterface
{
    /** Longest label kept (characters); longer labels are cut, the trail is navigation, not content. */
    public const MAX_LENGTH = 255;

    /** @var array{label: string|TranslatableInterface, domain: ?string, parameters: array<string, mixed>}|null */
    private ?array $pending = null;

    /** @var WeakReference<Request>|null main request the label was set for; null: set outside HTTP */
    private ?WeakReference $owner = null;

    public function __construct(
        private readonly ?RequestStack $requestStack = null,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Before the router, the firewall and any controller of the new request.
            KernelEvents::REQUEST => ['onKernelBoundary', 4096],
            KernelEvents::EXCEPTION => ['onKernelBoundary', 4096],
            KernelEvents::TERMINATE => ['onKernelBoundary', -4096],
            ConsoleEvents::COMMAND => ['clearCurrentLabel', 4096],
            ConsoleEvents::TERMINATE => ['clearCurrentLabel', -4096],
            // symfony/messenger is optional: event names as strings.
            'Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent' => ['clearCurrentLabel', 4096],
            'Symfony\Component\Messenger\Event\WorkerMessageHandledEvent' => ['clearCurrentLabel', -4096],
            'Symfony\Component\Messenger\Event\WorkerMessageFailedEvent' => ['clearCurrentLabel', -4096],
        ];
    }

    public function setCurrentLabel(string|Stringable|TranslatableInterface|null $label, ?string $translationDomain = null, array $translationParameters = []): void
    {
        if (!$label instanceof TranslatableInterface) {
            $label = self::normalize(null === $label ? '' : (string) $label);
        }
        if ('' === $label) {
            $this->clearCurrentLabel();

            return;
        }

        $main = $this->requestStack?->getMainRequest();
        // @igor-ignore - Request-scoped; cleared at every request / command / message boundary and owner-checked.
        $this->pending = [
            'label' => $label,
            'domain' => null === $translationDomain || '' === $translationDomain ? null : $translationDomain,
            'parameters' => $translationParameters,
        ];
        // @igor-ignore - Request-scoped; cleared at every request / command / message boundary.
        $this->owner = $main instanceof Request ? WeakReference::create($main) : null;
    }

    public function clearCurrentLabel(): void
    {
        $this->reset();
    }

    /**
     * Sub-requests (fragments, ESI) keep the label of their main request; only main-request boundaries clear it.
     */
    public function onKernelBoundary(KernelEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->clearCurrentLabel();
        }
    }

    public function reset(): void
    {
        $this->pending = null;
        $this->owner = null;
    }

    /**
     * Label for the trail being built now, translated when asked to; null: keep the item's label.
     */
    public function resolve(?string $locale = null): ?string
    {
        if (null === $this->pending) {
            return null;
        }

        if (!$this->belongsToCurrentRequest()) {
            $this->clearCurrentLabel();

            return null;
        }

        $label = $this->pending['label'];
        // Without a translator: message keys stay as they are, parameters are still substituted.
        $translator = $this->translator ?? new class implements TranslatorInterface {
            use TranslatorTrait;
        };
        if ($label instanceof TranslatableInterface) {
            $text = $label->trans($translator, $locale);
        } elseif (null !== $this->pending['domain']) {
            $text = $translator->trans($label, $this->pending['parameters'], $this->pending['domain'], $locale);
        } else {
            $text = $label;
        }

        $text = self::normalize($text);

        return '' === $text ? null : $text;
    }

    /**
     * Plain one-line text: control characters (newlines included) become spaces, runs of whitespace collapse, ends are
     * trimmed, invalid UTF-8 is dropped and the length is capped. HTML is not stripped: it is escaped on output.
     */
    public static function normalize(string $label): string
    {
        $clean = preg_replace('/[\p{Cc}\p{Zl}\p{Zp}\s]+/u', ' ', $label);
        if (null === $clean) {
            return '';
        }
        $clean = trim($clean);

        // symfony/string (twig/string-extra) brings symfony/polyfill-mbstring.
        return mb_substr($clean, 0, self::MAX_LENGTH);
    }

    private function belongsToCurrentRequest(): bool
    {
        $main = $this->requestStack?->getMainRequest();
        $owner = $this->owner?->get();

        if (null === $this->owner) {
            // Set outside HTTP (command, message, unit code): valid while no request is running.
            return !$main instanceof Request;
        }

        return $main instanceof Request && $owner === $main;
    }
}
