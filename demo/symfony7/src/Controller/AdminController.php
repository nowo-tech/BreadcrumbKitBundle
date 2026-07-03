<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(): Response
    {
        return $this->render('admin/users.html.twig');
    }

    /**
     * Cadena admin larga (6 migas): una ruta por método para registro explícito en el router.
     */
    #[Route('/long/w1', name: 'app_admin_long_w1')]
    public function longW1(): Response
    {
        return $this->renderLongTrail(1);
    }

    #[Route('/long/w1/w2', name: 'app_admin_long_w2')]
    public function longW2(): Response
    {
        return $this->renderLongTrail(2);
    }

    #[Route('/long/w1/w2/w3', name: 'app_admin_long_w3')]
    public function longW3(): Response
    {
        return $this->renderLongTrail(3);
    }

    #[Route('/long/w1/w2/w3/w4', name: 'app_admin_long_w4')]
    public function longW4(): Response
    {
        return $this->renderLongTrail(4);
    }

    #[Route('/long/w1/w2/w3/w4/w5', name: 'app_admin_long_w5')]
    public function longW5(): Response
    {
        return $this->renderLongTrail(5);
    }

    private function renderLongTrail(int $level, int $maxLevel = 5): Response
    {
        return $this->render('admin/long_trail.html.twig', [
            'level' => $level,
            'maxLevel' => $maxLevel,
        ]);
    }
}
