<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\EventListener\DataContainer;

use Contao\BackendUser;
use Contao\FilesModel;
use Contao\Image;
use Contao\StringUtil;
use InspiredMinds\ContaoFileUsage\Controller\ShowFileReferencesController;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ShowReferencesButtonCallback
{
    public function __construct(
        private readonly UrlGeneratorInterface $router,
        private readonly AdapterInterface $cache,
        private readonly TranslatorInterface $translator,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(array $record): string
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof BackendUser || !$user->hasAccess('showreferences', 'fop')) {
            return '';
        }

        $file = FilesModel::findByPath(urldecode((string) $record['id']));

        if (null === $file || 'file' !== $file->type) {
            return '';
        }

        $uuid = StringUtil::binToUuid($file->uuid);
        $refererId = $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id');
        $href = $this->router->generate(ShowFileReferencesController::class, ['uuid' => $uuid, 'ref' => $refererId]);
        $attributes = '';
        $image = 'bundles/contaofileusage/link.svg';

        $cacheItem = $this->cache->getItem($uuid);

        if ($cacheItem->isHit()) {
            /** @var ResultsCollection $results */
            $results = $cacheItem->get();

            if (!$results->count()) {
                $attributes = ' style="opacity:0.33"';
                $image = 'bundles/contaofileusage/link-off.svg';
                $title = $this->translator->trans('button_no_file_usage', ['%filename%' => basename($file->path)], 'ContaoFileUsage');
            } else {
                $title = $this->translator->trans('button_show_file_usage', ['%filename%' => basename($file->path)], 'ContaoFileUsage');
            }
        } else {
            $image = 'bundles/contaofileusage/search.svg';
            $attributes = ' style="opacity:0.66"';
            $title = $this->translator->trans('button_search_file_usage', ['%filename%' => basename($file->path)], 'ContaoFileUsage');
        }

        return '<a href="'.$href.'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($image).'</a> ';
    }
}
