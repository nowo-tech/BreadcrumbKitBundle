<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Controller\Dashboard;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Form\BreadcrumbItemType;
use Nowo\BreadcrumbKitBundle\Form\Dashboard\DashboardGetSearchType;
use Nowo\BreadcrumbKitBundle\Form\Dashboard\DashboardPostDeleteType;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/collections/{collectionId<\d+>}/items', name: 'nowo_breadcrumb_kit_dashboard_items_')]
final class ItemCrudController extends AbstractController
{
    use DashboardControllerTrait;
    use DashboardRedirectTrait;

    /**
     * @param array<string, string> $modalSizes
     */
    public function __construct(
        private readonly BreadcrumbCollectionRepository $collectionRepository,
        private readonly BreadcrumbItemRepository $itemRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $formFactory,
        private readonly TranslatorInterface $translator,
        private readonly array $modalSizes,
    ) {
    }

    /**
     * @return array<string, string>
     */
    private function dashboardRoutes(): array
    {
        return [
            'collections_index' => 'nowo_breadcrumb_kit_dashboard_collections_index',
            'collections_edit' => 'nowo_breadcrumb_kit_dashboard_collections_edit',
            'export' => 'nowo_breadcrumb_kit_dashboard_collections_export',
            'import' => ImportExportController::ROUTE_IMPORT,
        ];
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, int $collectionId): Response
    {
        $collection = $this->getCollectionOr404($collectionId);
        $searchQuery = trim((string) $request->query->get('q', ''));
        $items = $this->itemRepository->findForDashboardList(
            $collection,
            '' !== $searchQuery ? $searchQuery : null,
        );

        $searchForm = $this->createForm(DashboardGetSearchType::class, ['q' => $searchQuery], [
            'action' => $this->generateUrl('nowo_breadcrumb_kit_dashboard_items_index', ['collectionId' => $collectionId]),
            'method' => 'GET',
            'search_placeholder' => $this->translator->trans('dashboard.search_placeholder', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN),
        ]);

        return $this->render('@NowoBreadcrumbKitBundle/dashboard/item/index.html.twig', [
            'collection' => $collection,
            'items' => $items,
            'search_query' => $searchQuery,
            'search_form' => $searchForm,
            'dashboard_nav' => 'items',
            'dashboard_routes' => $this->dashboardRoutes(),
            'modal_classes' => self::resolveModalClasses($this->modalSizes),
            'breadcrumb_kit_page' => 'items_index',
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

        if ($request->isMethod('GET') && $request->query->get('_partial')) {
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
        $fromModal = $request->request->has('_modal');

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeItemJsonFields($item);
            $this->entityManager->persist($item);
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('dashboard.flash.item_created', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));

            return $this->redirectToRefererOr($request, 'nowo_breadcrumb_kit_dashboard_items_index', ['collectionId' => $collectionId]);
        }

        if ($form->isSubmitted() && !$form->isValid() && $fromModal) {
            return $this->render('@NowoBreadcrumbKitBundle/dashboard/_item_form_partial.html.twig', [
                'form' => $form,
                'title' => $this->translator->trans('dashboard.new_item_modal_title', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN),
                'collection' => $collection,
                'is_edit' => false,
            ]);
        }

        return $this->render('@NowoBreadcrumbKitBundle/dashboard/item/form.html.twig', [
            'form' => $form,
            'title' => $this->translator->trans('dashboard.item_new_page_title', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN),
            'collection' => $item->getCollection(),
            'dashboard_nav' => 'items',
            'dashboard_routes' => $this->dashboardRoutes(),
        ]);
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

        if ($request->isMethod('GET') && $request->query->get('_partial')) {
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
        $fromModal = $request->request->has('_modal');

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeItemJsonFields($item);
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('dashboard.flash.item_updated', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));

            return $this->redirectToRefererOr($request, 'nowo_breadcrumb_kit_dashboard_items_index', ['collectionId' => $collectionId]);
        }

        if ($form->isSubmitted() && !$form->isValid() && $fromModal) {
            return $this->render('@NowoBreadcrumbKitBundle/dashboard/_item_form_partial.html.twig', [
                'form' => $form,
                'title' => $editTitle,
                'collection' => $collection,
                'item' => $item,
                'is_edit' => true,
            ]);
        }

        return $this->render('@NowoBreadcrumbKitBundle/dashboard/item/form.html.twig', [
            'form' => $form,
            'title' => $editTitle,
            'collection' => $collection,
            'item' => $item,
            'dashboard_nav' => 'items',
            'dashboard_routes' => $this->dashboardRoutes(),
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, int $collectionId, int $id): Response
    {
        $collection = $this->getCollectionOr404($collectionId);
        $item = $this->itemRepository->find($id);
        if (!$item instanceof BreadcrumbItem || $item->getCollection()?->getId() !== $collection->getId()) {
            throw $this->createNotFoundException($this->translator->trans('dashboard.item_not_found', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        }

        $form = $this->formFactory->createNamedBuilder('delete_item_'.$id, DashboardPostDeleteType::class, null, [
            'action' => $this->generateUrl('nowo_breadcrumb_kit_dashboard_items_delete', ['collectionId' => $collectionId, 'id' => $id]),
            'method' => 'POST',
            'csrf_token_id' => 'delete_item_'.$id,
        ])->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->remove($item);
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('dashboard.flash.item_deleted', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        } elseif ($form->isSubmitted()) {
            $this->addFlash('danger', $this->translator->trans('dashboard.flash.csrf_invalid', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        }

        return $this->redirectToRefererOr($request, 'nowo_breadcrumb_kit_dashboard_items_index', ['collectionId' => $collection->getId()]);
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
                if (\is_string($k) && \is_string($v) && '' !== $v) {
                    $clean[$k] = $v;
                }
            }
            $item->setTranslations([] === $clean ? null : $clean);
        }
    }
}
