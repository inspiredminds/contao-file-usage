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

use Contao\DC_Folder;
use Contao\FilesModel;
use Contao\Image;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use InspiredMinds\ContaoFileUsage\Finder\FileUsageFinderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class FolderDataContainer extends DC_Folder
{
    protected static $breadcrumbSet = false;

    protected function generateTree($path, $intMargin, $mount = false, $blnProtected = true, $arrClipboard = null, $arrFound = [])
    {
        $container = System::getContainer();
        /** @var Request $request */
        $request = $container->get('request_stack')->getCurrentRequest();

        if ($request->query->get('unused')) {
            /** @var FileUsageFinderInterface $finder */
            $finder = $container->get('contao_file_usage.finder.file_usage');
            $references = $finder->findAll();
            $unused = [];

            foreach (FilesModel::findByType('file') ?? [] as $file) {
                $uuid = StringUtil::binToUuid($file->uuid);
                if (!isset($references[$uuid])) {
                    $unused[] = $file->path;
                }
            }

            if (!empty($arrFound)) {
                $unused = array_intersect($arrFound, $unused);
            }

            $arrFound = $unused;

            if (!self::$breadcrumbSet) {
                /** @var TranslatorInterface $translator */
                $translator = $container->get('translator');
                Message::addNew($translator->trans('file_usage_warning', [], 'ContaoFileUsage'));

                $links = [
                    Image::getHtml('filemounts.svg').' <a href="'.self::addToUrl('unused=').'" title="'.StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['selectAllNodes']).'">'.$GLOBALS['TL_LANG']['MSC']['filterAll'].'</a>',
                    Image::getHtml('folderO.svg').' '.($GLOBALS['TL_LANG']['tl_files']['unused'] ?? 'unused'),
                ];

                $GLOBALS['TL_DCA']['tl_files']['list']['sorting']['breadcrumb'] = ($GLOBALS['TL_DCA']['tl_files']['list']['sorting']['breadcrumb'] ?? '').$container->get('twig')->render('@ContaoFileUsage/files_breadcrumb_menu.html.twig', ['breadcrumb' => $links]);

                self::$breadcrumbSet = true;
            }
        }

        return parent::generateTree($path, $intMargin, $mount, $blnProtected, $arrClipboard, $arrFound);
    }
}
