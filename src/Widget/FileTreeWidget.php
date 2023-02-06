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

use Contao\File;
use Contao\FilesModel;
use Contao\FileTree;
use Contao\StringUtil;
use Contao\System;
use InspiredMinds\ContaoFileUsage\Controller\ReplaceFileReferencesController;

/**
 * Replacement for Contao's own fileTree widget to include an image replacement button.
 */
class FileTreeWidget extends FileTree
{
    protected function getPreviewImage(File $file, $info, $class = 'gimage')
    {
        $preview = parent::getPreviewImage($file, $info, $class);
        $model = FilesModel::findByPath($file->path);

        if (null === $model) {
            return $preview;
        }

        $container = System::getContainer();

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

        $preview .= '<a class="replace-image" href="'.$url.'" title="'.$translator->trans('replace_image', ['%path%' => $file->path]).'"><img src="/bundles/contaofileusage/repeat.svg" alt=""></a>';

        return $preview;
    }
}
