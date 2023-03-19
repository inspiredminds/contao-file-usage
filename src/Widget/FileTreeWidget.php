<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Widget;

use Contao\BackendUser;
use Contao\File;
use Contao\FilesModel;
use Contao\FileTree;
use Contao\StringUtil;
use Contao\System;
use InspiredMinds\ContaoFileUsage\Controller\ReplaceFileReferencesController;
use Symfony\Component\Security\Core\Security;

/**
 * Replacement for Contao's own fileTree widget to include an image replacement button.
 */
class FileTreeWidget extends FileTree
{
    protected function getPreviewImage(File $file, $info, $class = 'gimage')
    {
        $preview = parent::getPreviewImage($file, $info, $class);
        $container = System::getContainer();
        /** @var Security $security */
        $security = $container->get('security.helper');
        $user = $security->getUser();

        if (!$user instanceof BackendUser || !$user->hasAccess('replacereferences', 'fop')) {
            return $preview;
        }

        $model = FilesModel::findByPath($file->path);

        if (null === $model) {
            return $preview;
        }

        $GLOBALS['TL_CSS'][] = 'bundles/contaofileusage/backend.css';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaofileusage/backend.js';

        /** @var UrlGeneratorInterface $router */
        $router = $container->get('router');
        /** @var Request $request */
        $request = $container->get('request_stack')->getCurrentRequest();
        $url = $router->generate(ReplaceFileReferencesController::class, [
            'fileUuid' => StringUtil::binToUuid($model->uuid),
            'sourceTable' => $this->dataContainer->table,
            'sourceId' => $this->dataContainer->id,
            'ref' => $request->attributes->get('_contao_referer_id'),
            'redirect' => base64_encode($request->getUri()),
        ]);

        /** @var TranslatorInterface $translator */
        $translator = $container->get('translator');

        $preview .= '<a class="replace-image" href="'.$url.'" title="'.$translator->trans('replace_image', ['%path%' => $file->path], 'ContaoFileUsage').'"><img src="/bundles/contaofileusage/repeat.svg" alt=""></a>';

        return $preview;
    }
}
