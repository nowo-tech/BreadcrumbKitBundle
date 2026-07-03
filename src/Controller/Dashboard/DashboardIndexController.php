<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/')]
final class DashboardIndexController extends AbstractController
{
    #[Route('', name: 'nowo_breadcrumb_kit_dashboard_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->redirectToRoute('nowo_breadcrumb_kit_dashboard_collections_index');
    }
}
