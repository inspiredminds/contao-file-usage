<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Replace;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use InspiredMinds\ContaoFileUsage\Result\DatabaseInsertTagResult;
use InspiredMinds\ContaoFileUsage\Result\DatabaseReferenceResult;
use InspiredMinds\ContaoFileUsage\Result\FileTreeMultipleResult;
use InspiredMinds\ContaoFileUsage\Result\FileTreeSingleResult;
use InspiredMinds\ContaoFileUsage\Result\ResultInterface;

class FileReferenceReplacer implements FileReferenceReplacerInterface
{
    private $db;
    private $framework;

    public function __construct(Connection $db, ContaoFramework $framework)
    {
        $this->db = $db;
        $this->framework = $framework;
    }

    public function replace(ResultInterface $result, string $oldUuid, string $newUuid): void
    {
        if ($result instanceof DatabaseReferenceResult && (!$result->getId() || !$result->getPk())) {
            return;
        }

        $this->framework->initialize();

        if ($result instanceof DatabaseInsertTagResult) {
            $oldUuid = $this->convertUuidToString($oldUuid);
            $newUuid = $this->convertUuidToString($newUuid);

            $this->db->executeQuery('
                UPDATE '.$this->db->quoteIdentifier($result->getTable()).'
                   SET '.$this->db->quoteIdentifier($result->getField()).' = REPLACE('.$this->db->quoteIdentifier($result->getField()).', ?, ?)
                 WHERE '.$this->db->quoteIdentifier($result->getPk()).' = ?',
                ['::'.$oldUuid, '::'.$newUuid, $result->getId()]
            );

            return;
        }

        if ($result instanceof FileTreeSingleResult) {
            $newUuid = $this->convertUuidToBin($newUuid);

            $this->db->update($result->getTable(), [$result->getField() => $newUuid], [$result->getPk() => $result->getId()]);

            return;
        }

        if ($result instanceof FileTreeMultipleResult) {
            $oldUuid = $this->convertUuidToBin($oldUuid);
            $newUuid = $this->convertUuidToBin($newUuid);

            $this->db->executeQuery('
                UPDATE '.$this->db->quoteIdentifier($result->getTable()).'
                   SET '.$this->db->quoteIdentifier($result->getField()).' = REPLACE('.$this->db->quoteIdentifier($result->getField()).', ?, ?)
                 WHERE '.$this->db->quoteIdentifier($result->getPk()).' = ?',
                [$oldUuid, $newUuid, $result->getId()]
            );

            $oldUuid = $this->convertUuidToString($oldUuid);
            $newUuid = $this->convertUuidToString($newUuid);

            $this->db->executeQuery('
                UPDATE '.$this->db->quoteIdentifier($result->getTable()).'
                   SET '.$this->db->quoteIdentifier($result->getField()).' = REPLACE('.$this->db->quoteIdentifier($result->getField()).', ?, ?)
                 WHERE '.$this->db->quoteIdentifier($result->getPk()).' = ?',
                [$oldUuid, $newUuid, $result->getId()]
            );

            return;
        }

        if ($result instanceof DatabaseReferenceResult) {
            $oldUuid = $this->convertUuidToBin($oldUuid);
            $newUuid = $this->convertUuidToBin($newUuid);

            $this->db->update($result->getTable(), [$result->getField() => $newUuid], [$result->getPk() => $result->getId(), $result->getField() => $oldUuid]);

            $oldUuid = $this->convertUuidToString($oldUuid);
            $newUuid = $this->convertUuidToString($newUuid);

            $this->db->update($result->getTable(), [$result->getField() => $newUuid], [$result->getPk() => $result->getId(), $result->getField() => $oldUuid]);

            return;
        }
    }

    private function convertUuidToBin(string $uuid): string
    {
        if (Validator::isBinaryUuid($uuid)) {
            return $uuid;
        }

        return StringUtil::uuidToBin($uuid);
    }

    private function convertUuidToString(string $uuid): string
    {
        if (Validator::isStringUuid($uuid)) {
            return $uuid;
        }

        return StringUtil::binToUuid($uuid);
    }
}
