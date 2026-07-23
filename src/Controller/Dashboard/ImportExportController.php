<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Controller\Dashboard;

use Nowo\BreadcrumbKitBundle\Form\Dashboard\ImportBreadcrumbType;
use Nowo\BreadcrumbKitBundle\NowoBreadcrumbKitBundle;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbExporter;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/')]
final class ImportExportController extends AbstractController
{
    use DashboardRedirectTrait;

    public const ROUTE_IMPORT = 'nowo_breadcrumb_kit_dashboard_import';
    public const ROUTE_EXPORT_ALL = 'nowo_breadcrumb_kit_dashboard_export_all';

    public function __construct(
        private readonly BreadcrumbExporter $breadcrumbExporter,
        private readonly BreadcrumbImporter $breadcrumbImporter,
        private readonly int $importMaxBytes,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, string>
     */
    private function dashboardRoutes(): array
    {
        return [
            'collections_index' => 'nowo_breadcrumb_kit_dashboard_collections_index',
            'import' => self::ROUTE_IMPORT,
            'export_all' => self::ROUTE_EXPORT_ALL,
        ];
    }

    #[Route(path: 'export', name: 'nowo_breadcrumb_kit_dashboard_export_all', methods: ['GET'])]
    public function exportAll(): Response
    {
        $payload = $this->breadcrumbExporter->exportAll();
        $payload['breadcrumbKitExport'] = '1';
        $json = json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        $response = new StreamedResponse(static function () use ($json): void {
            echo $json;
        });
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="breadcrumb-kit-export.json"');

        return $response;
    }

    #[Route(path: 'import', name: 'nowo_breadcrumb_kit_dashboard_import', methods: ['GET', 'POST'])]
    public function import(Request $request): Response
    {
        $form = $this->createForm(ImportBreadcrumbType::class, null, [
            'action' => $this->generateUrl(self::ROUTE_IMPORT),
        ]);
        $form->handleRequest($request);
        $isModal = $request->request->has('_modal') || $request->query->get('_partial');

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{file?: UploadedFile, strategy?: string} $data */
            $data = $form->getData();
            $file = $data['file'] ?? null;
            if ($file instanceof UploadedFile) {
                $size = $file->getSize();
                if (false !== $size && $size > $this->importMaxBytes) {
                    $mb = (string) (int) ($this->importMaxBytes / 1024 / 1024);
                    $this->addFlash('danger', $this->translator->trans('dashboard.flash.import_file_too_large', ['%max%' => $mb], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));

                    return $this->renderImportResponse($request, $form, $isModal);
                }
                try {
                    $content = $file->getContent();
                    $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    $this->addFlash('danger', $this->translator->trans('dashboard.flash.import_json_invalid', ['%message%' => $e->getMessage()], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));

                    return $this->renderImportResponse($request, $form, $isModal);
                }
                if (!\is_array($decoded)) {
                    $this->addFlash('danger', $this->translator->trans('dashboard.flash.import_json_not_object', [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
                } else {
                    $decoded = BreadcrumbImporter::normalizeImportPayload($decoded);
                    $formatErrors = $this->validateImportPayloadFormat($decoded);
                    if ([] !== $formatErrors) {
                        foreach ($formatErrors as $err) {
                            $this->addFlash('danger', $this->translator->trans($err, [], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
                        }

                        return $this->redirectToRefererOr($request, $this->dashboardRoutes()['collections_index']);
                    }
                    $rawStrategy = $data['strategy'] ?? BreadcrumbImporter::STRATEGY_SKIP_EXISTING;
                    $strategy = \in_array($rawStrategy, [BreadcrumbImporter::STRATEGY_REPLACE, BreadcrumbImporter::STRATEGY_SKIP_EXISTING], true)
                        ? $rawStrategy
                        : BreadcrumbImporter::STRATEGY_SKIP_EXISTING;
                    $result = $this->breadcrumbImporter->import($decoded, $strategy);
                    foreach ($result['errors'] as $err) {
                        $this->addFlash('danger', $err);
                    }
                    if ([] === $result['errors']) {
                        $this->addFlash('success', $this->translator->trans('dashboard.flash.import_done', [
                            '%created%' => (string) $result['created'],
                            '%updated%' => (string) $result['updated'],
                            '%skipped%' => (string) $result['skipped'],
                        ], NowoBreadcrumbKitBundle::TRANSLATION_DOMAIN));
                    }

                    return $this->redirectToRefererOr($request, $this->dashboardRoutes()['collections_index']);
                }
            }

            return $this->redirectToRefererOr($request, $this->dashboardRoutes()['collections_index']);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->renderImportResponse($request, $form, $isModal);
        }

        return $this->renderImportResponse($request, $form, $isModal);
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function renderImportResponse(Request $request, FormInterface $form, bool $usePartial): Response
    {
        $vars = [
            'form' => $form,
            'dashboard_routes' => $this->dashboardRoutes(),
        ];
        if ($usePartial || $request->query->get('_partial')) {
            return $this->render('@NowoBreadcrumbKitBundle/dashboard/_import_partial.html.twig', $vars);
        }

        return $this->render('@NowoBreadcrumbKitBundle/dashboard/import.html.twig', $vars);
    }

    /**
     * @param array<mixed> $decoded
     *
     * @return list<string>
     */
    private function validateImportPayloadFormat(array $decoded): array
    {
        if (isset($decoded['collection']) && \is_array($decoded['collection']) && isset($decoded['items']) && \is_array($decoded['items'])) {
            return [];
        }
        if (isset($decoded['collections']) && \is_array($decoded['collections'])) {
            return [];
        }
        if (array_is_list($decoded)) {
            return ['import.error.invalid_root'];
        }

        return ['import.error.invalid_shape'];
    }
}
