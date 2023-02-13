<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\EventListener;

use Contao\CoreBundle\Event\MenuEvent;
use InspiredMinds\ContaoFileUsage\Controller\ShowFileReferencesController;
use Symfony\Component\HttpFoundation\RequestStack;

class BackendMenuListener
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function __invoke(MenuEvent $event): void
    {
        $tree = $event->getTree();

        if ('mainMenu' !== $tree->getName()) {
            return;
        }

        $systemNode = $tree->getChild('system');

        if (null === $systemNode) {
            return;
        }

        $filesNode = $systemNode->getChild('files');

        if (null === $filesNode) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (ShowFileReferencesController::class === $request->get('_controller')) {
            $filesNode->setCurrent(true);
        }
    }
}
