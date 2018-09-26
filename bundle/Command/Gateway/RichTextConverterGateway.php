<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformXmlTextFieldTypeBundle\Command\Gateway;

use Doctrine\DBAL\Connection;
use PDO;

class RichTextConverterGateway
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getContentTypeIds($contentTypeIdentifiers)
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('c.identifier, c.id')
            ->from('ezcontentclass', 'c')
            ->where(
                $query->expr()->in(
                    'c.identifier',
                    ':contentTypeIdentifiers'
                )
            )
            ->setParameter(':contentTypeIdentifiers', $contentTypeIdentifiers, Connection::PARAM_STR_ARRAY);

        $statement = $query->execute();

        $columns = $statement->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($columns as $column) {
            $result[$column['identifier']] = $column['id'];
        }

        return $result;
    }

    public function countContentTypeFieldsByFieldType($fieldTypeIdentifier)
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('count(a.id)')
            ->from('ezcontentclass_attribute', 'a')
            ->where(
                $query->expr()->eq(
                    'a.data_type_string',
                    ':datatypestring'
                )
            )
            ->setParameter(':datatypestring', $fieldTypeIdentifier);

        $statement = $query->execute();

        return (int) $statement->fetchColumn();
    }

    public function getContentTypeFieldTypeUpdateQuery($fromFieldTypeIdentifier, $toFieldTypeIdentifier)
    {
        $updateQuery = $this->connection->createQueryBuilder();
        $updateQuery->update('ezcontentclass_attribute')
            ->set('data_type_string', ':newdatatypestring')
            // was tagPreset in ezxmltext, unused in RichText
            ->set('data_text2', ':datatext2')
            ->where(
                $updateQuery->expr()->eq(
                    'data_type_string',
                    ':olddatatypestring'
                )
            )
            ->setParameters([
                ':newdatatypestring' => $toFieldTypeIdentifier,
                ':datatext2' => null,
                ':olddatatypestring' => $fromFieldTypeIdentifier,
            ]);

        return $updateQuery;
    }

    public function getRowCountOfContentObjectAttributes($fieldTypeIdentifier, $contentId = null)
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('count(a.id)')
            ->from('ezcontentobject_attribute', 'a')
            ->where(
                $query->expr()->eq(
                    'a.data_type_string',
                    ':datatypestring'
                )
            )
            ->setParameter(':datatypestring', $fieldTypeIdentifier);

        if ($contentId !== null) {
            $query->andWhere(
                $query->expr()->eq(
                    'a.contentobject_id',
                    ':contentid'
                )
            )
                ->setParameter(':contentid', $contentId);
        }

        $statement = $query->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * Get the result set for specified field rows.
     * Note that if $contentId !== null, then $offset and $limit will be ignored.
     *
     * @param string $fieldTypeIdentifier
     * @param int $contentId
     * @param int $offset
     * @param int $limit
     *
     * @return \Doctrine\DBAL\Driver\Statement
     */
    public function getFieldRowsResultSet($fieldTypeIdentifier, $contentId, $offset, $limit)
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('a.*')
            ->from('ezcontentobject_attribute', 'a')
            ->where(
                $query->expr()->eq(
                    'a.data_type_string',
                    ':datatypestring'
                )
            )
            ->orderBy('a.id')
            ->setParameter(':datatypestring', $fieldTypeIdentifier);

        if ($contentId === null) {
            $query->setFirstResult($offset)
                ->setMaxResults($limit);
        } else {
            $query->andWhere(
                $query->expr()->eq(
                    'a.contentobject_id',
                    ':contentid'
                )
            )
                ->setParameter(':contentid', $contentId);
        }

        return $query->execute();
    }

    public function getUpdateFieldRowQuery($id, $version, $datatext)
    {
        $updateQuery = $this->connection->createQueryBuilder();
        $updateQuery->update('ezcontentobject_attribute', 'a')
            ->set('a.data_type_string', ':datatypestring')
            ->set('a.data_text', ':datatext')
            ->where(
                $updateQuery->expr()->eq(
                    'a.id',
                    ':id'
                )
            )
            ->andWhere(
                $updateQuery->expr()->eq(
                    'a.version',
                    ':version'
                )
            )
            ->setParameters([
                ':datatypestring' => 'ezrichtext',
                ':datatext' => $datatext,
                ':id' => $id,
                ':version' => $version,
            ]);

        return $updateQuery;
    }
}
