<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\EventListener\DataContainer;

use Contao\BackendUser;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class FilesPermissionsCallback
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(): void
    {
        if (!$user = $this->tokenStorage->getToken()?->getUser()) {
            return;
        }

        if (!$user instanceof BackendUser) {
            throw new \RuntimeException('Unexpected user instance.');
        }

        if ($user->isAdmin) {
            return;
        }

        if (!$user->hasAccess('unused', 'fop')) {
            $this->denyUnused();
        }
    }

    private function denyUnused(): void
    {
        unset($GLOBALS['TL_DCA']['tl_files']['list']['global_operations']['unused']);

        if ($this->requestStack->getCurrentRequest()->query->get('unused')) {
            throw new AccessDeniedException('No permission to show unused files.');
        }
    }
}
