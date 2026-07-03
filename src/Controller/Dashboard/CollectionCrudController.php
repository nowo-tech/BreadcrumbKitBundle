<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Controller\Dashboard;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException as DbalUniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Form\BreadcrumbCollectionType;
use Nowo\BreadcrumbKitBundle\Form\Dashboard\DashboardPostDeleteType;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/collections', name: 'nowo_breadcrumb_kit_dashboard_collections_')]
final class CollectionCrudController extends AbstractController
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
        private readonly ParameterBagInterface $parameterBag,
        private readonly BreadcrumbExporter $breadcrumbExporter,
        private readonly FormFactoryInterface $formFactory,
        private readonly TranslatorInterface $translator,
        private readonly bool $paginationEnabled,
        private readonly int $paginationPerPage,
        private readonly array $modalSizes,
    ) {
    }

    /**
     * @return array<string, string>
     */
    private function dashboardRoutes(): array
    {
        return [
            'index' => 'nowo_breadcrumb_kit_dashboard_collections_index',
            'new' => 'nowo_breadcrumb_kit_dashboard_collections_new',
            'edit' => 'nowo_breadcrumb_kit_dashboard_collections_edit',
            'delete' => 'nowo_breadcrumb_kit_dashboard_collections_delete',
            'export' => 'nowo_breadcrumb_kit_dashboard_collections_export',
            'import' => ImportExportController::ROUTE_IMPORT,
            'export_all' => ImportExportController::ROUTE_EXPORT_ALL,
        ];
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));

        if ($this->paginationEnabled) {
            $perPage = $this->paginationPerPage;
            $total = $this->collectionRepository->countForDashboard($search);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
            $page = max(1, min($page, $totalPages > 0 ? $totalPages : 1));
            $offset = ($page - 1) * $perPage;
            $collections = $this->collectionRepository->findForDashboard($search, $offset, $perPage);
            $pagination = [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ];
        } else {
            $collections = $this->collectionRepository->findForDashboard($search, 0);
            $pagination = null;
        }

        $collectionIds = [];
        foreach ($collections as $collection) {
            $collectionId = $collection->getId();
            if (null !== $collectionId) {
                $collectionIds[] = (int) $collectionId;
            }
        }
        $collectionItemCounts = $this->itemRepository->countForCollections($collectionIds);

        return $this->render('@NowoBreadcrumbKitBundle/dashboard/collection/index.html.twig', [
            'collections' => $collections,
            'collection_item_counts' => $collectionItemCounts,
            'search' => $search,
            'pagination' => $pagination,
            'dashboard_nav' => 'collections',
            'dashboard_routes' => $this->dashboardRoutes(),
            'modal_classes' => self::resolveModalClasses($this->modalSizes),
            'breadcrumb_kit_page' => 'collections_index',
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $collection = new BreadcrumbCollection();
        $opts = $this->collectionFormOptions();
        if ($request->isMethod('GET') && $request->query->get('_partial')) {
            $form = $this->createForm(BreadcrumbCollectionType::class, $collection, array_merge($opts, [
                'action' => $this->generateUrl('nowo_breadcrumb_kit_dashboard_collections_new'),
            ]));

            return $this->render('@NowoBreadcrumbKitBundle/dashboard/_collection_form_partial.html.twig', [
                'form' => $form,
                'title' => $this->translator->trans('dashboard.new_collection_modal_title', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN),
                'is_edit' => false,
            ]);
        }

        $form = $this->createForm(BreadcrumbCollectionType::class, $collection, $opts);
        $form->handleRequest($request);
        $fromModal = $request->request->has('_modal');

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->persist($collection);
                $this->entityManager->flush();
                $this->addFlash('success', $this->translator->trans('dashboard.flash.collection_created', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));

                return $this->redirectToRefererOr($request, 'nowo_breadcrumb_kit_dashboard_collections_edit', ['id' => $collection->getId()]);
            } catch (\Throwable $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    $this->addFlash('danger', $this->translator->trans('dashboard.flash.collection_duplicate', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
                    if ($fromModal) {
                        return $this->render('@NowoBreadcrumbKitBundle/dashboard/_collection_form_partial.html.twig', [
                            'form' => $form,
                            'title' => $this->translator->trans('dashboard.new_collection_modal_title', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN),
                            'is_edit' => false,
                        ]);
                    }
                } else {
                    throw $e;
                }
            }
        }

        if ($form->isSubmitted() && !$form->isValid() && $fromModal) {
            return $this->render('@NowoBreadcrumbKitBundle/dashboard/_collection_form_partial.html.twig', [
                'form' => $form,
                'title' => $this->translator->trans('dashboard.new_collection_modal_title', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN),
                'is_edit' => false,
            ]);
        }

        return $this->render('@NowoBreadcrumbKitBundle/dashboard/collection/form.html.twig', [
            'form' => $form,
            'title' => $this->translator->trans('dashboard.new_collection_modal_title', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN),
            'collection' => null,
            'dashboard_nav' => 'collections',
            'dashboard_routes' => $this->dashboardRoutes(),
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $collection = $this->collectionRepository->find($id);
        if (!$collection instanceof BreadcrumbCollection) {
            throw $this->createNotFoundException($this->translator->trans('dashboard.collection_not_found', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        }

        $opts = $this->collectionFormOptions();
        $editTitle = $this->translator->trans('dashboard.edit_collection_modal_title', ['%code%' => $collection->getCode()], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN);
        if ($request->isMethod('GET') && $request->query->get('_partial')) {
            $form = $this->createForm(BreadcrumbCollectionType::class, $collection, array_merge($opts, [
                'action' => $this->generateUrl('nowo_breadcrumb_kit_dashboard_collections_edit', ['id' => $id]),
            ]));

            return $this->render('@NowoBreadcrumbKitBundle/dashboard/_collection_form_partial.html.twig', [
                'form' => $form,
                'title' => $editTitle,
                'is_edit' => true,
            ]);
        }

        $form = $this->createForm(BreadcrumbCollectionType::class, $collection, $opts);
        $form->handleRequest($request);
        $fromModal = $request->request->has('_modal');

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->flush();
                $this->addFlash('success', $this->translator->trans('dashboard.flash.collection_updated', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));

                return $this->redirectToRefererOr($request, 'nowo_breadcrumb_kit_dashboard_collections_edit', ['id' => $collection->getId()]);
            } catch (\Throwable $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    $this->addFlash('danger', $this->translator->trans('dashboard.flash.collection_duplicate', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
                    if ($fromModal) {
                        return $this->render('@NowoBreadcrumbKitBundle/dashboard/_collection_form_partial.html.twig', [
                            'form' => $form,
                            'title' => $editTitle,
                            'is_edit' => true,
                        ]);
                    }
                } else {
                    throw $e;
                }
            }
        }

        if ($form->isSubmitted() && !$form->isValid() && $fromModal) {
            return $this->render('@NowoBreadcrumbKitBundle/dashboard/_collection_form_partial.html.twig', [
                'form' => $form,
                'title' => $editTitle,
                'is_edit' => true,
            ]);
        }

        return $this->render('@NowoBreadcrumbKitBundle/dashboard/collection/form.html.twig', [
            'form' => $form,
            'title' => $editTitle,
            'collection' => $collection,
            'dashboard_nav' => 'collections',
            'dashboard_routes' => $this->dashboardRoutes(),
        ]);
    }

    #[Route('/{id<\d+>}/export', name: 'export', methods: ['GET'])]
    public function exportCollection(int $id): Response
    {
        $collection = $this->collectionRepository->find($id);
        if (!$collection instanceof BreadcrumbCollection) {
            throw $this->createNotFoundException($this->translator->trans('dashboard.collection_not_found', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        }

        $payload = $this->breadcrumbExporter->exportCollection($collection);
        $payload['breadcrumbKitExport'] = '1';
        $json = json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $collection->getCode()) ?? 'collection';
        $suffix = '' !== $collection->getContextKey() ? '-'.preg_replace('/[^a-zA-Z0-9_-]/', '_', $collection->getContextKey()) : '';
        $filename = 'breadcrumb-'.$safe.$suffix.'-export.json';

        $response = new StreamedResponse(static function () use ($json): void {
            echo $json;
        });
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    #[Route('/{id<\d+>}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $collection = $this->collectionRepository->find($id);
        if (!$collection instanceof BreadcrumbCollection) {
            throw $this->createNotFoundException($this->translator->trans('dashboard.collection_not_found', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        }

        $form = $this->formFactory->createNamedBuilder('delete_collection_'.$id, DashboardPostDeleteType::class, null, [
            'action' => $this->generateUrl('nowo_breadcrumb_kit_dashboard_collections_delete', ['id' => $id]),
            'method' => 'POST',
            'csrf_token_id' => 'delete_collection_'.$id,
        ])->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->remove($collection);
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('dashboard.flash.collection_deleted', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        } elseif ($form->isSubmitted()) {
            $this->addFlash('danger', $this->translator->trans('dashboard.flash.csrf_invalid', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        }

        return $this->redirectToRefererOr($request, 'nowo_breadcrumb_kit_dashboard_collections_index');
    }

    /**
     * @return array{inline_edit_access_keys: list<string>}
     */
    private function collectionFormOptions(): array
    {
        $map = $this->parameterBag->get('nowo_breadcrumb_kit.inline_edit.access_services');
        if (!\is_array($map)) {
            return ['inline_edit_access_keys' => []];
        }

        $keys = [];
        foreach (array_keys($map) as $k) {
            if (\is_string($k) && '' !== $k) {
                $keys[] = $k;
            }
        }

        return ['inline_edit_access_keys' => $keys];
    }

    private function isUniqueConstraintViolation(\Throwable $e): bool
    {
        if ($e instanceof DbalUniqueConstraintViolationException) {
            return true;
        }

        $prev = $e->getPrevious();
        while ($prev instanceof \Throwable) {
            if ($prev instanceof DbalUniqueConstraintViolationException) {
                return true;
            }
            $prev = $prev->getPrevious();
        }

        return false;
    }
}
