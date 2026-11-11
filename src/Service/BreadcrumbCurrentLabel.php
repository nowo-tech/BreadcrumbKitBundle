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
 * Scope (audit MF-M1): the label belongs to the route of the request being handled when it was set (and its scalar
 * route parameters), optionally to one collection / context key; trails built for anything else keep their labels.
 *
 * Worker safety (FrankenPHP, kernel reset false): the label is dropped when a main request starts, ends or fails, when a
 * console command starts or ends and when a Messenger worker receives or finishes a message; on top of that it only
 * applies while the main request it was set for is still the current one. `reset()` clears it too, but nothing relies on
 * kernel.reset.
 */
final class BreadcrumbCurrentLabel implements BreadcrumbCurrentLabelInterface, BreadcrumbCurrentLabelProviderInterface, EventSubscriberInterface, ResetInterface
{
    /** Longest label kept (characters); longer labels are cut, the trail is navigation, not content. */
    public const MAX_LENGTH = 255;

    /**
     * @var array{
     *     label: string|TranslatableInterface,
     *     domain: ?string,
     *     parameters: array<string, mixed>,
     *     route: string,
     *     routeParams: array<string, scalar|null>,
     *     collection: ?string,
     *     contextKey: ?string,
     * }|null
     */
    private ?array $pending = null;

    /** @var WeakReference<Request>|null main request the label was set for */
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

    /**
     * Outside a routed request (command, message, fragment without a route) there is no trail to name: no-op.
     */
    public function setCurrentLabel(
        string|Stringable|TranslatableInterface|null $label,
        ?string $translationDomain = null,
        array $translationParameters = [],
        ?string $collection = null,
        ?string $contextKey = null,
    ): void {
        if (!$label instanceof TranslatableInterface) {
            $label = self::normalize(null === $label ? '' : (string) $label);
        }
        $request = $this->requestStack?->getCurrentRequest();
        $main = $this->requestStack?->getMainRequest();
        $route = $request instanceof Request ? self::routeOf($request) : null;
        if ('' === $label || null === $route || !$main instanceof Request) {
            $this->clearCurrentLabel();

            return;
        }

        // @igor-ignore - Request-scoped; cleared at every request / command / message boundary and owner-checked.
        $this->pending = [
            'label' => $label,
            'domain' => null === $translationDomain || '' === $translationDomain ? null : $translationDomain,
            'parameters' => $translationParameters,
            'route' => $route,
            'routeParams' => self::routeParamsOf($request),
            'collection' => $collection,
            'contextKey' => $contextKey,
        ];
        // @igor-ignore - Request-scoped; cleared at every request / command / message boundary.
        $this->owner = WeakReference::create($main);
    }

    public function clearCurrentLabel(): void
    {
        $this->reset();
    }

    /**
     * Sub-requests (fragments, ESI) never clear the label of their main request; only main-request boundaries do.
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

    public function currentLabelFor(Request $request, string $collectionCode, string $contextKey, ?string $locale = null): ?string
    {
        $pending = $this->pending;
        if (null === $pending) {
            return null;
        }

        $main = $this->requestStack?->getMainRequest();
        if (!$main instanceof Request || $this->owner?->get() !== $main) {
            // Stale: a later request on the same worker, or no request any more.
            $this->clearCurrentLabel();

            return null;
        }

        if ($pending['route'] !== self::routeOf($request)
            || $pending['routeParams'] !== self::routeParamsOf($request)
            || (null !== $pending['collection'] && $pending['collection'] !== $collectionCode)
            || (null !== $pending['contextKey'] && $pending['contextKey'] !== $contextKey)) {
            return null;
        }

        $label = $pending['label'];
        // Without a translator: message keys stay as they are, parameters are still substituted.
        $translator = $this->translator ?? new class implements TranslatorInterface {
            use TranslatorTrait;
        };
        if ($label instanceof TranslatableInterface) {
            $text = $label->trans($translator, $locale);
        } elseif (null !== $pending['domain']) {
            $text = $translator->trans($label, $pending['parameters'], $pending['domain'], $locale);
        } else {
            $text = $label;
        }

        $text = self::normalize($text);

        return '' === $text ? null : $text;
    }

    /**
     * Plain one-line text: format characters (bidi controls, zero-width characters) are removed, control characters
     * (newlines included) become spaces, runs of whitespace collapse, ends are trimmed and the length is capped. A label
     * that is not valid UTF-8 is ignored as a whole (empty result). HTML is not stripped: it is escaped on output.
     */
    public static function normalize(string $label): string
    {
        $clean = preg_replace(['/\p{Cf}+/u', '/[\p{Cc}\p{Zl}\p{Zp}\s]+/u'], ['', ' '], $label);
        if (null === $clean) {
            return '';
        }
        $clean = trim($clean);

        // symfony/string (twig/string-extra) brings symfony/polyfill-mbstring.
        return mb_substr($clean, 0, self::MAX_LENGTH);
    }

    private static function routeOf(Request $request): ?string
    {
        $route = $request->attributes->get('_route');

        return \is_string($route) && '' !== $route ? $route : null;
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function routeParamsOf(Request $request): array
    {
        $params = $request->attributes->get('_route_params');
        $scalars = [];
        foreach (\is_array($params) ? $params : [] as $name => $value) {
            if (\is_string($name) && (null === $value || \is_scalar($value))) {
                $scalars[$name] = $value;
            }
        }
        ksort($scalars);

        return $scalars;
    }
}
