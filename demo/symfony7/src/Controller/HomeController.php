<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/shop', name: 'app_shop')]
    public function shop(): Response
    {
        return $this->render('home/shop.html.twig');
    }

    #[Route('/product/{id}', name: 'app_product_show', requirements: ['id' => '\d+'])]
    public function product(int $id): Response
    {
        return $this->render('home/product.html.twig', [
            'id' => $id,
        ]);
    }

    /**
     * Two items share this route; the row with the most matching static_route_params wins (sales vs support).
     */
    #[Route('/topics/{section}', name: 'app_section_show', requirements: ['section' => 'sales|support'])]
    public function section(string $section): Response
    {
        return $this->render('home/section.html.twig', [
            'section' => $section,
        ]);
    }

    /** No breadcrumb_item for this route → empty trail (breadcrumb_render outputs nothing). */
    #[Route('/demo/no-match', name: 'app_demo_no_breadcrumb_match')]
    public function noBreadcrumbMatch(): Response
    {
        return $this->render('demo/no_match.html.twig');
    }
}
