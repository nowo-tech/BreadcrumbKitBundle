<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Controller\Dashboard;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Dashboard\DashboardPages;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Form\BreadcrumbItemType;
use Nowo\BreadcrumbKitBundle\Form\Dashboard\DashboardGetSearchType;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/collections/{collectionId<\d+>}/items', name: 'nowo_breadcrumb_kit_dashboard_items_')]
final class ItemCrudController extends AbstractController
{
    use DashboardControllerTrait;
    use DashboardRedirectTrait;

    public function __construct(
        private readonly BreadcrumbCollectionRepository $collectionRepository,
        private readonly BreadcrumbItemRepository $itemRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $formFactory,
        private readonly TranslatorInterface $translator,
        private readonly bool $paginationEnabled,
        private readonly int $paginationPerPage,
        private readonly DashboardPages $pages,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, int $collectionId): Response
    {
        $collection = $this->getCollectionOr404($collectionId);
        $searchQuery = trim((string) $request->query->get('q', ''));
        $search = '' !== $searchQuery ? $searchQuery : null;
        $page = max(1, (int) $request->query->get('page', 1));
        $total = 0;
        $perPage = $this->paginationPerPage;

        if ($this->paginationEnabled) {
            $total = $this->itemRepository->countForDashboardList($collection, $search);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
            $page = max(1, min($page, $totalPages));
            $items = $this->itemRepository->findForDashboardList($collection, $search, ($page - 1) * $perPage, $perPage);
        } else {
            $items = $this->itemRepository->findForDashboardList($collection, $search);
        }

        $searchForm = $this->createForm(DashboardGetSearchType::class, ['q' => $searchQuery], [
            'action' => $this->generateUrl(DashboardPages::ROUTE_ITEMS, ['collectionId' => $collectionId]),
            'method' => 'GET',
            'search_placeholder' => 'dashboard.search_placeholder',
            'search_label' => $this->translator->trans('dashboard.search', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN),
        ]);

        return $this->pages->render('items_index', [
            'page' => $this->pages->itemsIndex(
                $collection,
                $items,
                $searchForm->createView(),
                $searchQuery,
                DashboardPages::pagination($this->paginationEnabled, $page, $perPage, $total, DashboardPages::ROUTE_ITEMS, '' !== $searchQuery ? ['q' => $searchQuery] : [], ['collectionId' => $collectionId]),
                fn (int $id) => $this->deleteForm($collectionId, $id)->createView(),
            ),
            'collection' => $collection,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, int $collectionId): Response
    {
        $collection = $this->getCollectionOr404($collectionId);
        $item = new BreadcrumbItem();
        $item->setCollection($collection);
        $item->setStaticRouteParams([]);
        $item->setLinkEnabled(true);

        $itemFormOpts = [
            'collection' => $collection,
            'exclude_item' => null,
        ];

        if ($request->isMethod('GET') && ($request->query->get('_partial') || $request->isXmlHttpRequest())) {
            $form = $this->createForm(BreadcrumbItemType::class, $item, array_merge($itemFormOpts, [
                'action' => $this->generateUrl('nowo_breadcrumb_kit_dashboard_items_new', ['collectionId' => $collectionId]),
            ]));

            return $this->render('@NowoBreadcrumbKitBundle/dashboard/_item_form_partial.html.twig', [
                'form' => $form,
                'title' => $this->translator->trans('dashboard.new_item_modal_title', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN),
                'collection' => $collection,
                'is_edit' => false,
            ]);
        }

        $form = $this->createForm(BreadcrumbItemType::class, $item, $itemFormOpts);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeItemJsonFields($item);
            $this->entityManager->persist($item);
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('dashboard.flash.item_created', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));

            return $this->redirectToRefererOr($request, 'nowo_breadcrumb_kit_dashboard_items_index', ['collectionId' => $collectionId]);
        }

        return $this->renderFormPage($form, $collection, null);
    }

    #[Route('/{id<\d+>}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $collectionId, int $id): Response
    {
        $collection = $this->getCollectionOr404($collectionId);
        $item = $this->itemRepository->find($id);
        if (!$item instanceof BreadcrumbItem || $item->getCollection()?->getId() !== $collection->getId()) {
            throw $this->createNotFoundException($this->translator->trans('dashboard.item_not_found', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        }

        $itemFormOpts = [
            'collection' => $collection,
            'exclude_item' => $item,
        ];
        $editTitle = $this->translator->trans('dashboard.edit_item_modal_title', ['%id%' => (string) $item->getId()], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN);

        if ($request->isMethod('GET') && ($request->query->get('_partial') || $request->isXmlHttpRequest())) {
            $form = $this->createForm(BreadcrumbItemType::class, $item, array_merge($itemFormOpts, [
                'action' => $this->generateUrl('nowo_breadcrumb_kit_dashboard_items_edit', ['collectionId' => $collectionId, 'id' => $id]),
            ]));

            return $this->render('@NowoBreadcrumbKitBundle/dashboard/_item_form_partial.html.twig', [
                'form' => $form,
                'title' => $editTitle,
                'collection' => $collection,
                'item' => $item,
                'is_edit' => true,
            ]);
        }

        $form = $this->createForm(BreadcrumbItemType::class, $item, $itemFormOpts);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeItemJsonFields($item);
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('dashboard.flash.item_updated', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));

            return $this->redirectToRefererOr($request, 'nowo_breadcrumb_kit_dashboard_items_index', ['collectionId' => $collectionId]);
        }

        return $this->renderFormPage($form, $collection, $item);
    }

    /**
     * POST deletes (CSRF); GET renders the no-JS confirmation page (UiKit page/confirm, Action::fallbackHref).
     */
    #[Route('/{id<\d+>}/delete', name: 'delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, int $collectionId, int $id): Response
    {
        $collection = $this->getCollectionOr404($collectionId);
        $item = $this->itemRepository->find($id);
        if (!$item instanceof BreadcrumbItem || $item->getCollection()?->getId() !== $collection->getId()) {
            throw $this->createNotFoundException($this->translator->trans('dashboard.item_not_found', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        }

        $form = $this->deleteForm($collectionId, $id);
        if (!$request->isMethod('POST')) {
            return $this->pages->render('item_delete', ['page' => $this->pages->itemDelete($collection, $item, $form->createView()), 'collection' => $collection, 'item' => $item]);
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->remove($item);
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('dashboard.flash.item_deleted', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        } elseif ($form->isSubmitted()) {
            $this->addFlash('danger', $this->translator->trans('dashboard.flash.csrf_invalid', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        }

        return $this->redirectToListOr($request, DashboardPages::ROUTE_ITEMS, ['collectionId' => $collectionId]);
    }

    /**
     * @param FormInterface<BreadcrumbItem> $form
     */
    private function renderFormPage(FormInterface $form, BreadcrumbCollection $collection, ?BreadcrumbItem $item): Response
    {
        $id = $item?->getId();

        return $this->pages->render('item_form', [
            'page' => $this->pages->itemForm($form->createView(), $collection, $item, null !== $id ? $this->deleteForm((int) $collection->getId(), (int) $id)->createView() : null),
            'collection' => $collection,
            'item' => $item,
        ], $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    /**
     * @return FormInterface<null>
     */
    private function deleteForm(int $collectionId, int $id): FormInterface
    {
        return $this->createDeletePostForm($this->generateUrl(DashboardPages::ROUTE_ITEM_DELETE, ['collectionId' => $collectionId, 'id' => $id]), 'delete_item_'.$id);
    }

    private function getCollectionOr404(int $collectionId): BreadcrumbCollection
    {
        $collection = $this->collectionRepository->find($collectionId);
        if (!$collection instanceof BreadcrumbCollection) {
            throw $this->createNotFoundException($this->translator->trans('dashboard.collection_not_found', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        }

        return $collection;
    }

    private function normalizeItemJsonFields(BreadcrumbItem $item): void
    {
        $static = $item->getStaticRouteParams();
        $item->setStaticRouteParams(null === $static || [] === $static ? [] : $static);

        $tr = $item->getTranslations();
        if (\is_array($tr)) {
            $clean = [];
            foreach ($tr as $k => $v) {
                if ('' !== $v) {
                    $clean[$k] = $v;
                }
            }
            $item->setTranslations([] === $clean ? null : $clean);
        }
    }
}
