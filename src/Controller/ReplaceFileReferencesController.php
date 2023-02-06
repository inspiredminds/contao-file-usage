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

use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\FileTree;
use Contao\System;
use InspiredMinds\ContaoFileUsage\FileUsageFinderInterface;
use InspiredMinds\ContaoFileUsage\Result\DatabaseReferenceResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class ReplaceFileReferencesController
{
    private $twig;
    private $framework;
    private $translator;
    private $urlGenerator;
    private $fileUsageFinder;
    private $csrfTokenManager;
    private $csrfTokenName;

    public function __construct(Environment $twig, ContaoFramework $framework, TranslatorInterface $translator, UrlGeneratorInterface $urlGenerator, FileUsageFinderInterface $fileUsageFinder, ContaoCsrfTokenManager $csrfTokenManager, string $csrfTokenName)
    {
        $this->twig = $twig;
        $this->framework = $framework;
        $this->translator = $translator;
        $this->urlGenerator = $urlGenerator;
        $this->fileUsageFinder = $fileUsageFinder;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->csrfTokenName = $csrfTokenName;
    }

    /**
     * @Route("/contao/replace-file-reference/{fileUuid}/{sourceTable}/{sourceId}",
     *     name=ReplaceFileReferencesController::class,
     *     defaults={"_scope": "backend"}
     * )
     */
    public function indexAction(Request $request, string $fileUuid, string $sourceTable, string $sourceId)
    {
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
            $fileWidget->value = Dbafs::addResource($request->request->get('value'))->uuid;

            return new Response($fileWidget->generate());
        }

        if ($redirect = $request->query->get('redirect')) {
            $backUrl = base64_decode($redirect, true);
        } else {
            $backUrl = System::getReferer(false, $sourceTable);
        }

        $usages = $this->fileUsageFinder->find($file->uuid);
        $usageResults = [];

        foreach ($usages as $usage) {
            // Only display database reference results for now
            if (!$usage instanceof DatabaseReferenceResult) {
                continue;
            }

            $table = $usage->getTable();
            $usageResult = ['result' => $usage];

            if ($module = $this->getModuleForTable($table)) {
                $url = $this->urlGenerator->generate('contao_backend', [
                    'do' => $module,
                    'ref' => $request->attributes->get('_contao_referer_id'),
                    'table' => $table,
                    'id' => $usage->id,
                    'act' => 'edit',
                    'rt' => $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue(),
                ]);

                $usageResult['url'] = $url;
                $usageResult['module'] = $module;
            }

            $usageResults[] = $usageResult;
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

        return new Response($this->twig->render('@ContaoFileUsage/replace_file_reference.html.twig', [
            'file' => $file,
            'back_url' => $backUrl,
            'usages' => $usageResult,
            'sourceTable' => $sourceTable,
            'sourceId' => $sourceId,
            'requestToken' => $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue(),
            'fileWidget' => $fileWidget->parse(),
            'fileManagerUrl' => $fileManagerUrl,
            'uploadUrl' => $uploadUrl,
        ]));
    }

    private function getModuleForTable(string $table): ?string
    {
        foreach ($GLOBALS['BE_MOD'] as $category) {
            foreach ($category as $moduleKey => $module) {
                if (\in_array($table, $module['tables'] ?? [], true)) {
                    return $moduleKey;
                }
            }
        }

        return null;
    }
}
