<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Nowo\BreadcrumbKitBundle\Contract\BreadcrumbInlineEditAccessCheckerInterface;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbInlineEditContext;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves whether to expose the inline editor and which dashboard URL to load in the modal iframe.
 */
final readonly class BreadcrumbInlineEditResolver
{
    public function __construct(
        private RequestStack $requestStack,
        private BreadcrumbCollectionRepository $collectionRepository,
        private BreadcrumbLoader $breadcrumbLoader,
        private ContainerInterface $inlineEditAccessCheckerLocator,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private ?TokenStorageInterface $tokenStorage,
        private ?string $queryParamName,
        private bool $dashboardEnabled,
    ) {
    }

    public function resolve(string $collectionCode, string $contextKey = ''): BreadcrumbInlineEditContext
    {
        if (!$this->dashboardEnabled || null === $this->queryParamName || '' === $this->queryParamName) {
            return new BreadcrumbInlineEditContext();
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return new BreadcrumbInlineEditContext();
        }

        if (!$this->isQueryParamTruthy($request, $this->queryParamName)) {
            return new BreadcrumbInlineEditContext();
        }

        $collection = $this->collectionRepository->findOneByCodeAndContextKey($collectionCode, $contextKey);
        if (!$collection instanceof BreadcrumbCollection) {
            return new BreadcrumbInlineEditContext();
        }

        $accessKey = $collection->getInlineEditAccessKey();
        if (null === $accessKey || '' === $accessKey) {
            return new BreadcrumbInlineEditContext();
        }

        if (!$this->inlineEditAccessCheckerLocator->has($accessKey)) {
            return new BreadcrumbInlineEditContext();
        }

        $checker = $this->inlineEditAccessCheckerLocator->get($accessKey);
        if (!$checker instanceof BreadcrumbInlineEditAccessCheckerInterface) {
            return new BreadcrumbInlineEditContext();
        }

        $user = $this->resolveUser();
        if (!$checker->canUseInlineBreadcrumbEditor($request, $user)) {
            return new BreadcrumbInlineEditContext();
        }

        $collectionId = $collection->getId();
        if (null === $collectionId) {
            return new BreadcrumbInlineEditContext();
        }

        $item = $this->breadcrumbLoader->findMatchingItemForCurrentRequest($collectionCode, $contextKey);

        try {
            $iframeUrl = $this->buildDashboardUrl($collectionId, $item);
        } catch (ExceptionInterface) {
            return new BreadcrumbInlineEditContext();
        }

        $titleKey = $item instanceof BreadcrumbItem
            ? 'breadcrumb.inline_edit.modal_title_edit'
            : 'breadcrumb.inline_edit.modal_title_new';
        $title = $this->translator->trans($titleKey, [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN);

        return new BreadcrumbInlineEditContext(
            show: true,
            iframeUrl: $iframeUrl,
            modalTitle: $title,
        );
    }

    private function buildDashboardUrl(int $collectionId, ?BreadcrumbItem $item): string
    {
        if ($item instanceof BreadcrumbItem && null !== $item->getId()) {
            return $this->urlGenerator->generate('nowo_breadcrumb_kit_dashboard_items_edit', [
                'collectionId' => $collectionId,
                'id' => $item->getId(),
            ]);
        }

        return $this->urlGenerator->generate('nowo_breadcrumb_kit_dashboard_items_new', [
            'collectionId' => $collectionId,
        ]);
    }

    private function resolveUser(): ?UserInterface
    {
        if (null === $this->tokenStorage) {
            return null;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return null;
        }

        $user = $token->getUser();

        return $user instanceof UserInterface ? $user : null;
    }

    private function isQueryParamTruthy(Request $request, string $param): bool
    {
        if (!$request->query->has($param)) {
            return false;
        }

        $v = $request->query->get($param);
        if (\is_array($v)) {
            return false;
        }

        if (\is_bool($v)) {
            return $v;
        }

        $s = strtolower(trim((string) $v));

        return \in_array($s, ['1', 'true', 'yes', 'on'], true);
    }
}
