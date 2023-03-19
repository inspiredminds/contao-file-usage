<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Controller;

use Contao\BackendUser;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\System;
use InspiredMinds\ContaoFileUsage\Finder\FileUsageFinderInterface;
use InspiredMinds\ContaoFileUsage\Result\ResultEnhancerInterface;
use InspiredMinds\ContaoFileUsage\Result\Results;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Twig\Environment;

class ShowFileReferencesController
{
    private $twig;
    private $framework;
    private $cache;
    private $finder;
    private $enhancer;
    private $security;
    private $csrfTokenManager;
    private $csrfTokenName;

    public function __construct(
        Environment $twig,
        ContaoFramework $framework,
        AdapterInterface $cache,
        FileUsageFinderInterface $finder,
        ResultEnhancerInterface $enhancer,
        Security $security,
        ContaoCsrfTokenManager $csrfTokenManager,
        string $csrfTokenName
    ) {
        $this->twig = $twig;
        $this->framework = $framework;
        $this->cache = $cache;
        $this->finder = $finder;
        $this->enhancer = $enhancer;
        $this->security = $security;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->csrfTokenName = $csrfTokenName;
    }

    /**
     * @Route("/contao/show-file-references/{uuid}",
     *     name=ShowFileReferencesController::class,
     *     defaults={"_scope": "backend"}
     * )
     */
    public function __invoke(Request $request, string $uuid): Response
    {
        $user = $this->security->getUser();

        if (!$user instanceof BackendUser || !$user->hasAccess('showreferences', 'fop')) {
            throw new AccessDeniedException('No permission to show file references.');
        }

        $this->framework->initialize();

        if ($redirect = $request->request->get('_target_path', $request->query->get('redirect'))) {
            $backUrl = base64_decode($redirect, true);
        } else {
            $backUrl = System::getReferer(false);
        }

        if ('refresh_file_usage' === $request->request->get('FORM_SUBMIT')) {
            $this->finder->find();
        }

        $results = new Results($uuid);
        $item = $this->cache->getItem($uuid);

        if ($item->isHit()) {
            $results = $item->get();
        }

        $file = FilesModel::findByUuid($uuid);

        foreach ($results as $result) {
            $this->enhancer->enhance($result);
        }

        return new Response($this->twig->render('@ContaoFileUsage/show_file_references.html.twig', [
            'back_url' => $backUrl,
            'requestToken' => $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue(),
            'file' => $file,
            '_target_path' => base64_encode($backUrl),
            'results' => $results,
        ]));
    }
}
