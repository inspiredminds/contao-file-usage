<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\EventListener\DataContainer;

use Contao\FilesModel;
use Contao\Image;
use Contao\StringUtil;
use InspiredMinds\ContaoFileUsage\Controller\ShowFileReferencesController;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ShowReferencesButtonCallback
{
    private $router;
    private $cache;
    private $translator;

    public function __construct(UrlGeneratorInterface $router, AdapterInterface $cache, TranslatorInterface $translator)
    {
        $this->router = $router;
        $this->cache = $cache;
        $this->translator = $translator;
    }

    public function __invoke(array $record): string
    {
        $file = FilesModel::findByPath(urldecode($record['id']));

        if (null === $file || 'file' !== $file->type) {
            return '';
        }

        $uuid = StringUtil::binToUuid($file->uuid);
        $href = $this->router->generate(ShowFileReferencesController::class, ['uuid' => $uuid]);
        $attributes = '';
        $image = 'bundles/contaofileusage/link.svg';

        $cacheItem = $this->cache->getItem($uuid);

        if ($cacheItem->isHit()) {
            /** @var ResultsCollection $results */
            $results = $cacheItem->get();

            if (!$results->count()) {
                $attributes = ' style="opacity:0.5"';
                $title = $this->translator->trans('button_no_file_usage', ['%filename%' => basename($file->path)], 'ContaoFileUsage');
            } else {
                $title = $this->translator->trans('button_show_file_usage', ['%filename%' => basename($file->path)], 'ContaoFileUsage');
            }
        } else {
            $image = 'bundles/contaofileusage/search.svg';
            $attributes = ' style="opacity:0.5"';
            $title = $this->translator->trans('button_search_file_usage', ['%filename%' => basename($file->path)], 'ContaoFileUsage');
        }

        return '<a href="'.$href.'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($image).'</a> ';
    }
}
