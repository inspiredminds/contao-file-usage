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
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use InspiredMinds\ContaoFileUsage\FileUsageFinderInterface;
use InspiredMinds\ContaoFileUsage\Result\DatabaseInsertTagResult;
use InspiredMinds\ContaoFileUsage\Result\DatabaseReferenceResult;
use InspiredMinds\ContaoFileUsage\Result\ResultInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class ReplaceFileReferencesController
{
    public const SESSION_KEY = 'fileusage';

    private $twig;
    private $framework;
    private $translator;
    private $urlGenerator;
    private $fileUsageFinder;
    private $db;
    private $csrfTokenManager;
    private $csrfTokenName;

    public function __construct(Environment $twig, ContaoFramework $framework, TranslatorInterface $translator, UrlGeneratorInterface $urlGenerator, FileUsageFinderInterface $fileUsageFinder, Connection $db, ContaoCsrfTokenManager $csrfTokenManager, string $csrfTokenName)
    {
        $this->twig = $twig;
        $this->framework = $framework;
        $this->translator = $translator;
        $this->urlGenerator = $urlGenerator;
        $this->fileUsageFinder = $fileUsageFinder;
        $this->db = $db;
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

        $session = $request->getSession();
        $sessionData = $session->get(self::SESSION_KEY) ?? [];
        $uuid = StringUtil::binToUuid($file->uuid);

        if (isset($sessionData[$uuid])) {
            $usages = $sessionData[$uuid];
        } else {
            $usages = $this->fileUsageFinder->find($uuid);
            $sessionData[$uuid] = $usages;
            $session->set(self::SESSION_KEY, $sessionData);
        }

        if (Request::METHOD_POST === $request->getMethod() && 'replace_images' === $request->request->get('FORM_SUBMIT')) {
            $fileWidget->validate();

            if (!$fileWidget->hasErrors()) {
                $replaceElements = $request->request->get('elements');

                foreach ($replaceElements as $index) {
                    $this->replace($usages[(int) $index], $uuid, $fileWidget->value);
                }

                return new RedirectResponse($backUrl);
            }
        }

        $usageResults = [];

        foreach ($usages as $usage) {
            // Only display database reference results for now
            if (!$usage instanceof DatabaseReferenceResult) {
                continue;
            }

            // Ignore any references without an ID
            if (!$usage->getId()) {
                continue;
            }

            $table = $usage->getTable();
            $usageResult = ['result' => $usage];

            if ($module = $this->getModuleForTable($table)) {
                $url = $this->urlGenerator->generate('contao_backend', [
                    'do' => $module,
                    'ref' => $request->attributes->get('_contao_referer_id'),
                    'table' => $table,
                    'id' => $usage->getId(),
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

        return new Response($this->twig->render('@ContaoFileUsage/replace_file_references.html.twig', [
            'file' => $file,
            'back_url' => $backUrl,
            'usages' => $usageResults,
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

    private function replace(ResultInterface $result, string $oldUuid, string $newUuid): void
    {
        /** @var AbstractSchemaManager $schemaManager */
        $schemaManager = method_exists($this->db, 'createSchemaManager') ? $this->db->createSchemaManager() : $this->db->getSchemaManager();

        if ($result instanceof DatabaseInsertTagResult) {
            if (Validator::isBinaryUuid($newUuid)) {
                $newUuid = StringUtil::binToUuid($newUuid);
            }

            if (Validator::isBinaryUuid($oldUuid)) {
                $oldUuid = StringUtil::binToUuid($oldUuid);
            }

            $this->db->executeQuery("
                UPDATE ".$this->db->quoteIdentifier($result->getTable())." 
                   SET ".$this->db->quoteIdentifier($result->getField())." = REPLACE(".$this->db->quoteIdentifier($result->getField()).", ?, ?)
                 WHERE ".$this->db->quoteIdentifier($this->getPrimaryKey($result->getTable(), $schemaManager)." = ?")
            , ['::'.$oldUuid, '::'.$newUuid, $result->getId()]);
        }

        dd($result, $newUuid);
    }
    
    private function getPrimaryKey(string $table, AbstractSchemaManager $schemaManager): ?string
    {
        $table = $schemaManager->listTableDetails($table) ?? null;

        if (null === $table || !$table->hasPrimaryKey()) {
            return null;
        }

        return $table->getPrimaryKeyColumns()[0];
    }
}
