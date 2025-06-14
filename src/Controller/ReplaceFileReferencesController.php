<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Controller;

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\FileTree;
use Contao\StringUtil;
use Contao\System;
use InspiredMinds\ContaoFileUsage\Finder\FileUsageFinderInterface;
use InspiredMinds\ContaoFileUsage\Replace\FileReferenceReplacerInterface;
use InspiredMinds\ContaoFileUsage\Result\DatabaseReferenceResult;
use InspiredMinds\ContaoFileUsage\Result\ResultEnhancerInterface;
use InspiredMinds\ContaoFileUsage\Result\Results;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[Route(path: '/contao/replace-file-references/{fileUuid}/{sourceTable}', name: self::class, defaults: ['_scope' => 'backend'])]
class ReplaceFileReferencesController
{
    public const SESSION_KEY = 'fileusage';

    public function __construct(
        private readonly Environment $twig,
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly FileUsageFinderInterface $finder,
        private readonly FileReferenceReplacerInterface $replacer,
        private readonly AdapterInterface $cache,
        private readonly ResultEnhancerInterface $enhancer,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request, string $fileUuid, string $sourceTable): Response
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof BackendUser || !$user->hasAccess('showreferences', 'fop')) {
            throw new AccessDeniedException('No permission to replace file references.');
        }

        $this->framework->initialize();

        $file = FilesModel::findByUuid($fileUuid);

        if (null === $file) {
            throw new BadRequestHttpException('Could not find file with UUID "'.$fileUuid.'".');
        }

        $fileWidget = new FileTree([
            'id' => 'file',
            'name' => 'file',
            'label' => $this->translator->trans('tl_content.singleSRC.0', [], 'contao_tl_content'),
            'decodeEntities' => true,
            'filesOnly' => true,
            'fieldType' => 'radio',
            'mandatory' => true,
            'extensions' => Config::get('validImageTypes'),
        ]);

        if ($request->isXmlHttpRequest() && 'reloadFiletree' === $request->request->get('action') && $fileWidget->name === $request->request->get('name')) {
            $fileWidget->value = Dbafs::addResource(urldecode($request->request->get('value')))->uuid;

            return new Response($fileWidget->generate());
        }

        if ($redirect = $request->query->get('redirect')) {
            $backUrl = base64_decode($redirect, true);
        } else {
            $backUrl = System::getReferer(false, $sourceTable);
        }

        $session = $request->getSession();
        $uuid = StringUtil::binToUuid($file->uuid);

        if (Request::METHOD_POST === $request->getMethod() && $request->request->has('replace_images')) {
            $fileWidget->validate();

            if (!$fileWidget->hasErrors()) {
                $replaceElements = $request->request->all('elements');

                $usages = $session->get(self::SESSION_KEY);

                foreach ($replaceElements as $index) {
                    $this->replacer->replace($usages[(int) $index], $uuid, $fileWidget->value);
                }

                $this->cache->deleteItem($uuid);
                $session->remove(self::SESSION_KEY);

                return new RedirectResponse($request->request->get('_target_path', $backUrl));
            }
        }

        if ($request->request->has('refresh_file_usage')) {
            $this->finder->find();
        }

        $results = new Results($uuid);
        $item = $this->cache->getItem($uuid);

        if ($item->isHit()) {
            $results = $item->get();
        }

        $session->set(self::SESSION_KEY, $results);

        foreach ($results as $result) {
            // Only display database reference results for now
            if (!$result instanceof DatabaseReferenceResult) {
                continue;
            }

            // Ignore any references without an ID
            if (!$result->getId()) {
                continue;
            }

            $this->enhancer->enhance($result);
        }

        $fileManagerUrl = $this->urlGenerator->generate('contao_backend', [
            'do' => 'files',
            'fn' => \dirname($file->path),
            'rt' => $this->csrfTokenManager->getDefaultTokenValue(),
            'ref' => $request->attributes->get('_contao_referer_id'),
        ]);

        $uploadUrl = $this->urlGenerator->generate('contao_backend', [
            'do' => 'files',
            'act' => 'move',
            'mode' => 2,
            'pid' => \dirname($file->path),
            'rt' => $this->csrfTokenManager->getDefaultTokenValue(),
            'ref' => $request->attributes->get('_contao_referer_id'),
        ]);

        return new Response($this->twig->render('@ContaoFileUsage/replace_file_references.html.twig', [
            'file' => $file,
            'back_url' => $backUrl,
            'results' => $results,
            'sourceTable' => $sourceTable,
            'sourceId' => $request->get('sourceId'),
            'requestToken' => $this->csrfTokenManager->getDefaultTokenValue(),
            'fileWidget' => $fileWidget->parse(),
            'fileManagerUrl' => $fileManagerUrl,
            'uploadUrl' => $uploadUrl,
            'message' => $this->translator->trans('replace_image_warning', ['%fileManagerUrl%' => $fileManagerUrl, '%uploadUrl%' => $uploadUrl], 'ContaoFileUsage'),
        ]));
    }
}
