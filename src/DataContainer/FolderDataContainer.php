<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\DataContainer;

use Contao\BackendUser;
use Contao\Controller;
use Contao\DC_Folder;
use Contao\FilesModel;
use Contao\Image;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use InspiredMinds\ContaoFileUsage\Finder\FileUsageFinderInterface;
use InspiredMinds\ContaoFileUsage\Result\Results;
use InspiredMinds\ContaoImageAlternatives\DataContainer\FolderDriver;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\PathUtil\Path;

if (class_exists(FolderDriver::class)) {
    class FolderParent extends FolderDriver
    {
    }
} else {
    class FolderParent extends DC_Folder
    {
    }
}

class FolderDataContainer extends FolderParent
{
    protected static $firstIteration = false;

    protected function generateTree($path, $intMargin, $mount = false, $blnProtected = true, $arrClipboard = null, $arrFound = [])
    {
        $container = System::getContainer();
        /** @var Security $security */
        $security = $container->get('security.helper');
        $user = $security->getUser();

        if (!$user instanceof BackendUser || !$user->hasAccess('unused', 'fop')) {
            return parent::generateTree($path, $intMargin, $mount, $blnProtected, $arrClipboard, $arrFound);
        }

        /** @var Request $request */
        $request = $container->get('request_stack')->getCurrentRequest();

        if ($request->query->get('unused')) {
            /** @var AdapterInterface $cache */
            $cache = $container->get('contao_file_usage.file_usage_cache');

            if (!self::$firstIteration && $request->query->get('refresh')) {
                /** @var FileUsageFinderInterface $finder */
                $finder = $container->get('contao_file_usage.finder.file_usage');
                $finder->find();
            }

            if (!self::$firstIteration) {
                $unused = [];
                $projectDir = System::getContainer()->getParameter('kernel.project_dir');
                $relativePath = Path::makeRelative($path, $projectDir);

                /** @var FilesModel $file */
                foreach (FilesModel::findBy(["type = 'file'", 'path LIKE ?'], [rtrim($relativePath, '/').'/%']) ?? [] as $file) {
                    $uuid = StringUtil::binToUuid($file->uuid);

                    $item = $cache->getItem($uuid);

                    if ($item->isHit()) {
                        /** @var Results $results */
                        $results = $item->get();
                    }

                    if (!$item->isHit() || !$results->hasResults()) {
                        $unused[] = $file->path;
                    }
                }

                if (!empty($arrFound)) {
                    $unused = array_intersect($arrFound, $unused);
                }

                $arrFound = $unused;

                /** @var TranslatorInterface $translator */
                $translator = $container->get('translator');

                if (empty($arrFound)) {
                    Message::addInfo($translator->trans('unused_not_found', [], 'ContaoFileUsage'));
                    Controller::redirect(self::addToUrl('unused=&refresh='));
                } else {
                    Message::addNew($translator->trans('file_usage_warning', [], 'ContaoFileUsage'));

                    $links = [
                        Image::getHtml('filemounts.svg').' <a href="'.self::addToUrl('unused=&refresh=').'" title="'.StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['selectAllNodes']).'">'.$GLOBALS['TL_LANG']['MSC']['filterAll'].'</a>',
                        Image::getHtml('folderO.svg').' '.($GLOBALS['TL_LANG']['tl_files']['unused'] ?? 'unused'),
                    ];

                    $GLOBALS['TL_DCA']['tl_files']['list']['sorting']['breadcrumb'] = ($GLOBALS['TL_DCA']['tl_files']['list']['sorting']['breadcrumb'] ?? '').$container->get('twig')->render('@ContaoFileUsage/files_breadcrumb_menu.html.twig', ['breadcrumb' => $links]);
                }

                $GLOBALS['TL_DCA']['tl_files']['list']['global_operations']['unused']['href'] .= '&refresh=1';
                $GLOBALS['TL_DCA']['tl_files']['list']['global_operations']['unused']['icon'] = 'bundles/contaofileusage/refresh.svg';
                $GLOBALS['TL_DCA']['tl_files']['list']['global_operations']['unused']['label'] = $GLOBALS['TL_LANG']['tl_files']['refresh_unused'];

                self::$firstIteration = true;
            }
        }

        return parent::generateTree($path, $intMargin, $mount, $blnProtected, $arrClipboard, $arrFound);
    }
}
