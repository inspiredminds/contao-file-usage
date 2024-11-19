<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\EventListener;

use Contao\CoreBundle\Event\MenuEvent;
use InspiredMinds\ContaoFileUsage\Controller\ShowFileReferencesController;
use Symfony\Component\HttpFoundation\RequestStack;

class BackendMenuListener
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function __invoke(MenuEvent $event): void
    {
        $tree = $event->getTree();

        if ('mainMenu' !== $tree->getName()) {
            return;
        }

        if (!$systemNode = $tree->getChild('system')) {
            return;
        }

        if (!$filesNode = $systemNode->getChild('files')) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (ShowFileReferencesController::class === $request->get('_controller')) {
            $filesNode->setCurrent(true);
        }
    }
}
