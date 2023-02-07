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
use Contao\Controller;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\FileTree;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use InspiredMinds\ContaoFileUsage\Finder\FileUsageFinderInterface;
use InspiredMinds\ContaoFileUsage\Replace\FileReferenceReplacerInterface;
use InspiredMinds\ContaoFileUsage\Result\DatabaseReferenceResult;
use Symfony\Component\Cache\Adapter\AdapterInterface;
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
    private $replacer;
    private $cache;
    private $db;
    private $csrfTokenManager;
    private $csrfTokenName;

    private static $moduleKeyCache = [];

    public function __construct(Environment $twig, ContaoFramework $framework, TranslatorInterface $translator, UrlGeneratorInterface $urlGenerator, FileUsageFinderInterface $fileUsageFinder, FileReferenceReplacerInterface $replacer, AdapterInterface $cache, Connection $db, ContaoCsrfTokenManager $csrfTokenManager, string $csrfTokenName)
    {
        $this->twig = $twig;
        $this->framework = $framework;
        $this->translator = $translator;
        $this->urlGenerator = $urlGenerator;
        $this->fileUsageFinder = $fileUsageFinder;
        $this->replacer = $replacer;
        $this->cache = $cache;
        $this->db = $db;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->csrfTokenName = $csrfTokenName;
    }

    /**
     * @Route("/contao/replace-file-references/{fileUuid}/{sourceTable}/{sourceId}",
     *     name=ReplaceFileReferencesController::class,
     *     defaults={"_scope": "backend"}
     * )
     */
    public function __invoke(Request $request, string $fileUuid, string $sourceTable, string $sourceId): Response
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

        if (Request::METHOD_POST === $request->getMethod() && 'replace_images' === $request->request->get('FORM_SUBMIT')) {
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

        $usages = $this->fileUsageFinder->find($uuid, false);
        $session->set(self::SESSION_KEY, $usages);
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

            // Fetch the record
            $qb = $this->db->createQueryBuilder();
            $record = $qb
                ->select('*')
                ->from($usage->getTable())
                ->where($qb->expr()->eq($usage->getPk(), $usage->getId()))
                ->execute()
                ->fetchAssociative()
            ;

            // Get edit URL
            if ($module = $this->getModuleForTable(($record['ptable'] ?? null) ?: $table)) {
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

            foreach (['title', 'name', 'headline'] as $titleField) {
                if (!empty($record[$titleField])) {
                    $title = StringUtil::deserialize($record[$titleField], true);
                    $title = $title['value'] ?? reset($title);
                    $usageResult['title'] = StringUtil::decodeEntities(StringUtil::stripInsertTags($title));
                    break;
                }
            }

            // Try to fetch the parent
            if ($parentTable = $this->getParentTable($table, $record)) {
                if ($parentTitle = $this->getTitle($parentTable, (int) $record['pid'])) {
                    $usageResult['parent'] = $parentTitle;
                }

                if ($module = $this->getModuleForTable($parentTable)) {
                    $url = $this->urlGenerator->generate('contao_backend', [
                        'do' => $module,
                        'ref' => $request->attributes->get('_contao_referer_id'),
                        'table' => $parentTable,
                        'id' => (int) $record['pid'],
                        'act' => 'edit',
                        'rt' => $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue(),
                    ]);

                    $usageResult['parentEditUrl'] = $url;
                }
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
            'message' => $this->translator->trans('replace_image_warning', ['%fileManagerUrl%' => $fileManagerUrl, '%uploadUrl%' => $uploadUrl], 'ContaoFileUsage'),
        ]));
    }

    private function getModuleForTable(string $table): ?string
    {
        if (isset(self::$moduleKeyCache[$table])) {
            return self::$moduleKeyCache[$table];
        }

        foreach ($GLOBALS['BE_MOD'] as $category) {
            foreach ($category as $moduleKey => $module) {
                if (\in_array($table, $module['tables'] ?? [], true)) {
                    return $moduleKey;
                }
            }
        }

        return null;
    }

    private function getParentTable(string $table, array $record): ?string
    {
        Controller::loadDataContainer($table);
        $dca = $GLOBALS['TL_DCA'][$table] ?? null;

        if (empty($dca)) {
            return null;
        }

        $parentTable = null;

        if ($dca['config']['dynamicPtable'] ?? false) {
            $parentTable = ($record['ptable'] ?? null) ?: null;
        } else {
            $parentTable = $dca['config']['ptable'] ?? null;
        }

        return $parentTable;
    }

    private function getTitle(string $table, int $id): ?string
    {
        $qb = $this->db->createQueryBuilder();
        $record = $qb
            ->select('*')
            ->from($table)
            ->where($qb->expr()->eq('id', $id))
            ->execute()
            ->fetchAssociative()
        ;

        foreach (['title', 'name', 'headline'] as $titleField) {
            if (!empty($record[$titleField])) {
                $title = StringUtil::deserialize($record[$titleField], true);
                $title = $title['value'] ?? reset($title);

                return StringUtil::decodeEntities(StringUtil::stripInsertTags($title));
            }
        }

        return null;
    }
}
