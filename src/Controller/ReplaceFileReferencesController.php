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
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class ReplaceFileReferencesController
{
    public const SESSION_KEY = 'fileusage';

    private $twig;
    private $framework;
    private $translator;
    private $urlGenerator;
    private $finder;
    private $replacer;
    private $cache;
    private $enhancer;
    private $security;
    private $csrfTokenManager;
    private $csrfTokenName;

    public function __construct(
        Environment $twig,
        ContaoFramework $framework,
        TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator,
        FileUsageFinderInterface $finder,
        FileReferenceReplacerInterface $replacer,
        AdapterInterface $cache,
        ResultEnhancerInterface $enhancer,
        Security $security,
        ContaoCsrfTokenManager $csrfTokenManager,
        string $csrfTokenName
    ) {
        $this->twig = $twig;
        $this->framework = $framework;
        $this->translator = $translator;
        $this->urlGenerator = $urlGenerator;
        $this->finder = $finder;
        $this->replacer = $replacer;
        $this->cache = $cache;
        $this->enhancer = $enhancer;
        $this->security = $security;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->csrfTokenName = $csrfTokenName;
    }

    /**
     * @Route("/contao/replace-file-references/{fileUuid}/{sourceTable}",
     *     name=ReplaceFileReferencesController::class,
     *     defaults={"_scope": "backend"}
     * )
     */
    public function __invoke(Request $request, string $fileUuid, string $sourceTable): Response
    {
        $user = $this->security->getUser();

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
                $replaceElements = $request->request->get('elements');

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
            'rt' => $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue(),
            'ref' => $request->attributes->get('_contao_referer_id'),
        ]);

        $uploadUrl = $this->urlGenerator->generate('contao_backend', [
            'do' => 'files',
            'act' => 'move',
            'mode' => 2,
            'pid' => \dirname($file->path),
            'rt' => $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue(),
            'ref' => $request->attributes->get('_contao_referer_id'),
        ]);

        return new Response($this->twig->render('@ContaoFileUsage/replace_file_references.html.twig', [
            'file' => $file,
            'back_url' => $backUrl,
            'results' => $results,
            'sourceTable' => $sourceTable,
            'sourceId' => $request->get('sourceId'),
            'requestToken' => $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue(),
            'fileWidget' => $fileWidget->parse(),
            'fileManagerUrl' => $fileManagerUrl,
            'uploadUrl' => $uploadUrl,
            'message' => $this->translator->trans('replace_image_warning', ['%fileManagerUrl%' => $fileManagerUrl, '%uploadUrl%' => $uploadUrl], 'ContaoFileUsage'),
        ]));
    }
}
