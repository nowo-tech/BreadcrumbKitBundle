<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DemoController extends AbstractController
{
    #[Route('/demo/breadcrumb-trail', name: 'app_demo_breadcrumb_trail')]
    public function breadcrumbTrail(): Response
    {
        return $this->render('demo/breadcrumb_trail.html.twig');
    }

    #[Route('/demo/custom-template', name: 'app_demo_custom_template')]
    public function customTemplate(): Response
    {
        return $this->render('demo/custom_template.html.twig');
    }

    /**
     * Cadena larga (8 migas con Inicio): /demo/deep → …/s1/s2/s3/s4/s5/s6 — para probar responsive.
     */
    #[Route('/demo/deep', name: 'app_demo_deep')]
    #[Route('/demo/deep/s1', name: 'app_demo_deep_s1')]
    #[Route('/demo/deep/s1/s2', name: 'app_demo_deep_s2')]
    #[Route('/demo/deep/s1/s2/s3', name: 'app_demo_deep_s3')]
    #[Route('/demo/deep/s1/s2/s3/s4', name: 'app_demo_deep_s4')]
    #[Route('/demo/deep/s1/s2/s3/s4/s5', name: 'app_demo_deep_s5')]
    #[Route('/demo/deep/s1/s2/s3/s4/s5/s6', name: 'app_demo_deep_s6')]
    public function deepTrail(Request $request): Response
    {
        /** @var non-empty-string $route */
        $route = (string) $request->attributes->get('_route');
        $level = match ($route) {
            'app_demo_deep' => 0,
            'app_demo_deep_s1' => 1,
            'app_demo_deep_s2' => 2,
            'app_demo_deep_s3' => 3,
            'app_demo_deep_s4' => 4,
            'app_demo_deep_s5' => 5,
            'app_demo_deep_s6' => 6,
            default => 0,
        };

        return $this->render('demo/deep.html.twig', [
            'level' => $level,
            'maxLevel' => 6,
        ]);
    }
}
