<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Controller\Dashboard;

use Nowo\BreadcrumbKitBundle\Form\Dashboard\DashboardPostDeleteType;
use Symfony\Component\Form\FormInterface;

/**
 * Shared helpers for breadcrumb kit dashboard controllers.
 */
trait DashboardControllerTrait
{
    /**
     * @return FormInterface<null>
     */
    private function createDeletePostForm(string $action, string $csrfTokenId): FormInterface
    {
        return $this->formFactory->createNamedBuilder('', DashboardPostDeleteType::class, null, [
            'action' => $action,
            'method' => 'POST',
            'csrf_token_id' => $csrfTokenId,
        ])->getForm();
    }
}
