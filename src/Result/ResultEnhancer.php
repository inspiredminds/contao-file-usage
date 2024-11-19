<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Result;

use Contao\Controller;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ResultEnhancer implements ResultEnhancerInterface
{
    private static array $moduleKeyCache = [];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $db,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
    ) {
    }

    public function enhance(ResultInterface $result): void
    {
        // Only database reference results are supported
        if (!$result instanceof DatabaseReferenceResult) {
            return;
        }

        // Ignore any references without an ID
        if (!$result->getId()) {
            return;
        }

        $this->framework->initialize();

        $table = $result->getTable();

        // Get the current request
        $request = $this->requestStack->getCurrentRequest();

        // Fetch the database record
        $qb = $this->db->createQueryBuilder();
        $record = $qb
            ->select('*')
            ->from($table)
            ->where($qb->expr()->eq($result->getPk(), $result->getId()))
            ->execute()
            ->fetchAssociative()
        ;

        if (false === $record) {
            return;
        }

        // Get edit URL
        if ($module = $this->getModuleForTable($record['ptable'] ?? null ?: $table)) {
            $url = $this->urlGenerator->generate('contao_backend', [
                'do' => $module,
                'ref' => $request ? $request->attributes->get('_contao_referer_id') : '',
                'table' => $table,
                'id' => $result->getId(),
                'act' => 'edit',
                'rt' => $this->csrfTokenManager->getDefaultTokenValue(),
            ]);

            $result->setEditUrl($url);
            $result->setModule($module);
        }

        // Try to fetch a title
        foreach (['title', 'name', 'headline'] as $titleField) {
            if (!empty($record[$titleField])) {
                $title = StringUtil::deserialize($record[$titleField], true);
                $title = $title['value'] ?? reset($title);
                $result->setTitle(StringUtil::decodeEntities(StringUtil::stripInsertTags($title)));
                break;
            }
        }

        // Try to fetch the parent
        if ($parentTable = $this->getParentTable($table, $record)) {
            if ($parentTitle = $this->getTitle($parentTable, (int) $record['pid'])) {
                $result->setParentTitle($parentTitle);
            }

            if ($module = $this->getModuleForTable($parentTable)) {
                $url = $this->urlGenerator->generate('contao_backend', [
                    'do' => $module,
                    'ref' => $request->attributes->get('_contao_referer_id'),
                    'table' => $parentTable,
                    'id' => (int) $record['pid'],
                    'act' => 'edit',
                    'rt' => $this->csrfTokenManager->getDefaultTokenValue(),
                ]);

                $result->setParentEditUrl($url);
            }
        }
    }

    private function getModuleForTable(string $table): string|null
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

    private function getParentTable(string $table, array $record): string|null
    {
        Controller::loadDataContainer($table);
        $dca = $GLOBALS['TL_DCA'][$table] ?? null;

        if (empty($dca)) {
            return null;
        }

        $parentTable = null;

        if ($dca['config']['dynamicPtable'] ?? false) {
            $parentTable = $record['ptable'] ?? null ?: null;
        } else {
            $parentTable = $dca['config']['ptable'] ?? null;
        }

        return $parentTable;
    }

    private function getTitle(string $table, int $id): string|null
    {
        $qb = $this->db->createQueryBuilder();
        $record = $qb
            ->select('*')
            ->from($table)
            ->where($qb->expr()->eq('id', $id))
            ->execute()
            ->fetchAssociative()
        ;

        if (false === $record) {
            return null;
        }

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
