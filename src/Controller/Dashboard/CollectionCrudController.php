<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Controller\Dashboard;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException as DbalUniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Dashboard\DashboardPages;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Form\BreadcrumbCollectionType;
use Nowo\BreadcrumbKitBundle\Form\Dashboard\DashboardGetSearchType;
use Nowo\BreadcrumbKitBundle\Form\Dashboard\ImportBreadcrumbType;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbEntityManagerResetter;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[Route('/collections', name: 'nowo_breadcrumb_kit_dashboard_collections_')]
final class CollectionCrudController extends AbstractController
{
    use DashboardControllerTrait;
    use DashboardRedirectTrait;

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
        private readonly DashboardPages $pages,
        private readonly ?BreadcrumbEntityManagerResetter $entityManagerResetter = null,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $total = 0;
        $perPage = $this->paginationPerPage;

        if ($this->paginationEnabled) {
            $total = $this->collectionRepository->countForDashboard($search);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
            $page = max(1, min($page, $totalPages));
            $collections = $this->collectionRepository->findForDashboard($search, ($page - 1) * $perPage, $perPage);
        } else {
            $collections = $this->collectionRepository->findForDashboard($search, 0);
        }

        $collectionIds = [];
        foreach ($collections as $collection) {
            $collectionId = $collection->getId();
            if (null !== $collectionId) {
                $collectionIds[] = (int) $collectionId;
            }
        }
        $searchForm = $this->createForm(DashboardGetSearchType::class, ['q' => $search], [
            'action' => $this->generateUrl(DashboardPages::ROUTE_COLLECTIONS),
            'method' => 'GET',
            'search_placeholder' => 'dashboard.search_placeholder',
            'search_label' => $this->translator->trans('dashboard.search', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN),
        ]);
        $importForm = $this->createForm(ImportBreadcrumbType::class, null, [
            'action' => $this->generateUrl(DashboardPages::ROUTE_IMPORT),
        ]);

        return $this->pages->render('collections_index', [
            'page' => $this->pages->collectionsIndex(
                $collections,
                $this->itemRepository->countForCollections($collectionIds),
                $searchForm->createView(),
                $search,
                DashboardPages::pagination($this->paginationEnabled, $page, $perPage, $total, DashboardPages::ROUTE_COLLECTIONS, '' !== $search ? ['q' => $search] : []),
                fn (int $id) => $this->deleteForm($id)->createView(),
            ),
            'import_form' => $importForm->createView(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $collection = new BreadcrumbCollection();
        $opts = $this->collectionFormOptions();
        if ($request->isMethod('GET') && ($request->query->get('_partial') || $request->isXmlHttpRequest())) {
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

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->persist($collection);
                $this->entityManager->flush();
                $this->addFlash('success', $this->translator->trans('dashboard.flash.collection_created', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));

                return $this->redirectToRefererOr($request, 'nowo_breadcrumb_kit_dashboard_collections_edit', ['id' => $collection->getId()]);
            } catch (Throwable $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    $this->entityManagerResetter?->resetIfClosed();
                    $this->addFlash('danger', $this->translator->trans('dashboard.flash.collection_duplicate', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
                } else {
                    throw $e;
                }
            }
        }

        return $this->renderFormPage($form, null);
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
        if ($request->isMethod('GET') && ($request->query->get('_partial') || $request->isXmlHttpRequest())) {
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

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->flush();
                $this->addFlash('success', $this->translator->trans('dashboard.flash.collection_updated', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));

                return $this->redirectToRefererOr($request, 'nowo_breadcrumb_kit_dashboard_collections_edit', ['id' => $collection->getId()]);
            } catch (Throwable $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    $this->entityManagerResetter?->resetIfClosed();
                    $this->addFlash('danger', $this->translator->trans('dashboard.flash.collection_duplicate', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
                } else {
                    throw $e;
                }
            }
        }

        return $this->renderFormPage($form, $collection);
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

    /**
     * POST deletes (CSRF); GET renders the no-JS confirmation page (UiKit page/confirm, Action::fallbackHref).
     */
    #[Route('/{id<\d+>}/delete', name: 'delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, int $id): Response
    {
        $collection = $this->collectionRepository->find($id);
        if (!$collection instanceof BreadcrumbCollection) {
            throw $this->createNotFoundException($this->translator->trans('dashboard.collection_not_found', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        }

        $form = $this->deleteForm($id);
        if (!$request->isMethod('POST')) {
            return $this->pages->render('collection_delete', ['page' => $this->pages->collectionDelete($collection, $form->createView()), 'collection' => $collection]);
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->remove($collection);
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('dashboard.flash.collection_deleted', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        } elseif ($form->isSubmitted()) {
            $this->addFlash('danger', $this->translator->trans('dashboard.flash.csrf_invalid', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
        }

        return $this->redirectToListOr($request, DashboardPages::ROUTE_COLLECTIONS);
    }

    /**
     * @param FormInterface<BreadcrumbCollection> $form
     */
    private function renderFormPage(FormInterface $form, ?BreadcrumbCollection $collection): Response
    {
        $id = $collection?->getId();

        return $this->pages->render('collection_form', [
            'page' => $this->pages->collectionForm($form->createView(), $collection, null !== $id ? $this->deleteForm((int) $id)->createView() : null),
            'collection' => $collection,
        ], $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    /**
     * @return FormInterface<null>
     */
    private function deleteForm(int $id): FormInterface
    {
        return $this->createDeletePostForm($this->generateUrl(DashboardPages::ROUTE_COLLECTION_DELETE, ['id' => $id]), 'delete_collection_'.$id);
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

    private function isUniqueConstraintViolation(Throwable $e): bool
    {
        if ($e instanceof DbalUniqueConstraintViolationException) {
            return true;
        }

        $prev = $e->getPrevious();
        while ($prev instanceof Throwable) {
            if ($prev instanceof DbalUniqueConstraintViolationException) {
                return true;
            }
            $prev = $prev->getPrevious();
        }

        return false;
    }
}
