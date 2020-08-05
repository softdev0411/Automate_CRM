<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2020 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\ORM\DB;

use Espo\Core\Exceptions\Error;

use Espo\ORM\{
    Entity,
    Collection,
    EntityFactory,
    Metadata,
    DB\Query\BaseQuery as Query,
    EntityCollection,
    Sth2Collection,
};

use PDO;
use Exception;
use LogicException;

/**
 * Abstraction for DB. Mapping of Entity to DB. Supposed to be used only internally. Use repositories instead.
 */
abstract class BaseMapper implements Mapper
{
    public $pdo;

    protected $entityFactroy;

    protected $query;

    protected $metadata;

    protected $fieldsMapCache = [];

    protected $aliasesCache = [];

    protected $collectionClass = EntityCollection::class;

    protected $sthCollectionClass = Sth2Collection::class;

    public function __construct(PDO $pdo, EntityFactory $entityFactory, Query $query, Metadata $metadata)
    {
        $this->pdo = $pdo;
        $this->query = $query;
        $this->entityFactory = $entityFactory;
        $this->metadata = $metadata;
    }

    /**
     * Get a single entity from DB by ID.
     */
    public function selectById(Entity $entity, string $id, ?array $params = null) : ?Entity
    {
        $params = $params ?? [];

        if (!array_key_exists('whereClause', $params)) {
            $params['whereClause'] = [];
        }

        $params['whereClause']['id'] = $id;

        $sql = $this->query->createSelectQuery($entity->getEntityType(), $params);

        $ps = $this->pdo->query($sql);

        if ($ps) {
            foreach ($ps as $row) {
                $entity = $this->fromRow($entity, $row);
                $entity->setAsFetched();
                return $entity;
            }
        }
        return null;
    }

    /**
     * Get a number of entities in DB.
     */
    public function count(Entity $entity, ?array $params = null) : int
    {
        return (int) $this->aggregate($entity, $params, 'COUNT', 'id');
    }

    public function max(Entity $entity, ?array $params, string $attribute)
    {
        return $this->aggregate($entity, $params, 'MAX', $attribute);
    }

    public function min(Entity $entity, ?array $params, string $attribute)
    {
        return $this->aggregate($entity, $params, 'MIN', $attribute);
    }

    public function sum(Entity $entity, ?array $params, string $attribute)
    {
        return $this->aggregate($entity, $params, 'SUM', $attribute);
    }

    /**
     * Select enities from DB.
     */
    public function select(Entity $entity, ?array $params = null) : Collection
    {
        $sql = $this->query->createSelectQuery($entity->getEntityType(), $params);

        return $this->selectByQuery($entity, $sql, $params);
    }

    /**
     * Select enities from DB by a SQL query.
     */
    public function selectByQuery(Entity $entity, string $sql, ?array $params = null) : Collection
    {
        $params = $params ?? [];

        if ($params['returnSthCollection'] ?? false) {
            $collection = $this->createSthCollection($entity->getEntityType());
            $collection->setQuery($sql);
            $collection->setAsFetched();

            return $collection;
        }

        $dataList = [];
        $ps = $this->pdo->query($sql);
        if ($ps) {
            $dataList = $ps->fetchAll();
        }

        $collection = $this->createCollection($entity->getEntityType(), $dataList);
        $collection->setAsFetched();

        return $collection;
    }

    protected function createCollection(string $entityType, ?array $dataList = [])
    {
        return new $this->collectionClass($dataList, $entityType, $this->entityFactory);
    }

    protected function createSthCollection(string $entityType)
    {
        return new $this->sthCollectionClass($entityType, $this->entityFactory, $this->query, $this->pdo);;
    }

    public function aggregate(Entity $entity, ?array $params, string $aggregation, string $aggregationBy)
    {
        if (empty($aggregation) || !$entity->hasAttribute($aggregationBy)) {
            return null;
        }

        $params = $params ?? [];

        $params['aggregation'] = $aggregation;
        $params['aggregationBy'] = $aggregationBy;

        $sql = $this->query->createSelectQuery($entity->getEntityType(), $params);

        $ps = $this->pdo->query($sql);

        if ($ps) {
            foreach ($ps as $row) {
                return $row['value'];
            }
        }

        return null;
    }

    /**
     * Select related entities from DB.
     */
    public function selectRelated(Entity $entity, string $relationName, ?array $params = null)
    {
        return $this->selectRelatedInternal($entity, $relationName, $params);
    }

    protected function selectRelatedInternal(Entity $entity, string $relationName, ?array $params = null, bool $returnTotalCount = false)
    {
        $params = $params ?? [];

        $entityType = $entity->getEntityType();

        $relDefs = $entity->getRelations()[$relationName];

        if (!isset($relDefs['type'])) {
            throw new LogicException(
                "Missing 'type' in definition for relationship {$relationName} in {entityType} entity."
            );
        }

        if ($relDefs['type'] !== Entity::BELONGS_TO_PARENT) {
            if (!isset($relDefs['entity'])) {
                throw new LogicException(
                    "Missing 'entity' in definition for relationship {$relationName} in {entityType} entity."
                );
            }

            $relEntityType = (!empty($relDefs['class'])) ? $relDefs['class'] : $relDefs['entity'];
            $relEntity = $this->entityFactory->create($relEntityType);
        }

        if ($returnTotalCount) {
            $params['aggregation'] = 'COUNT';
            $params['aggregationBy'] = 'id';
        }

        if (empty($params['whereClause'])) {
            $params['whereClause'] = [];
        }

        $relType = $relDefs['type'];

        $keySet = $this->query->getKeys($entity, $relationName);

        $key = $keySet['key'];
        $foreignKey = $keySet['foreignKey'];

        switch ($relType) {
            case Entity::BELONGS_TO:
                $params['whereClause'][$foreignKey] = $entity->get($key);
                $params['offset'] = 0;
                $params['limit'] = 1;

                $sql = $this->query->createSelectQuery($relEntity->getEntityType(), $params);

                $ps = $this->pdo->query($sql);

                if (!$ps) {
                    return null;
                }

                if ($returnTotalCount) {
                    foreach ($ps as $row) {
                        return intval($row['value']);
                    }
                    return 0;
                }

                foreach ($ps as $row) {
                    $relEntity = $this->fromRow($relEntity, $row);
                    $relEntity->setAsFetched();

                    return $relEntity;
                }

                return null;

            case Entity::HAS_MANY:
            case Entity::HAS_CHILDREN:
            case Entity::HAS_ONE:
                $params['whereClause'][$foreignKey] = $entity->get($key);

                if ($relType == Entity::HAS_CHILDREN) {
                    $foreignType = $keySet['foreignType'];
                    $params['whereClause'][$foreignType] = $entity->getEntityType();
                }

                if ($relType == Entity::HAS_ONE) {
                    $params['offset'] = 0;
                    $params['limit'] = 1;
                }

                if (!empty($relDefs['conditions']) && is_array($relDefs['conditions'])) {
                    $params['whereClause'][] = $relDefs['conditions'];
                }

                $resultDataList = [];

                $sql = $this->query->createSelectQuery($relEntity->getEntityType(), $params);

                if (!$returnTotalCount) {
                    if (!empty($params['returnSthCollection']) && $relType !== Entity::HAS_ONE) {
                        $collection = $this->createSthCollection($relEntity->getEntityType());
                        $collection->setQuery($sql);
                        $collection->setAsFetched();
                        return $collection;
                    }
                }

                $ps = $this->pdo->query($sql);

                if (!$ps) {
                    return null;
                }

                if ($returnTotalCount) {
                    foreach ($ps as $row) {
                        return intval($row['value']);
                    }
                    return 0;
                }

                $resultDataList = $ps->fetchAll();

                if ($relType == Entity::HAS_ONE) {
                    if (count($resultDataList)) {
                        $relEntity = $this->fromRow($relEntity, $resultDataList[0]);
                        $relEntity->setAsFetched();

                        return $relEntity;
                    }
                    return null;
                }

                $collection = $this->createCollection($relEntity->getEntityType(), $resultDataList);
                $collection->setAsFetched();

                return $collection;

            case Entity::MANY_MANY:
                $additionalColumnsConditions = null;
                if (!empty($params['additionalColumnsConditions'])) {
                    $additionalColumnsConditions = $params['additionalColumnsConditions'];
                }

                $params['joins'] = $params['joins'] ?? [];

                $params['joins'][] = $this->getManyManyJoin($entity, $relationName, $additionalColumnsConditions);


                $params['relationName'] = $relDefs['relationName'];

                $sql = $this->query->createSelectQuery($relEntity->getEntityType(), $params);

                $resultDataList = [];

                if (!$returnTotalCount) {
                    if (!empty($params['returnSthCollection'])) {
                        $collection = $this->createSthCollection($relEntity->getEntityType());
                        $collection->setQuery($sql);
                        $collection->setAsFetched();
                        return $collection;
                    }
                }

                $ps = $this->pdo->query($sql);

                if (!$ps) {
                    return null;
                }

                if ($returnTotalCount) {
                    foreach ($ps as $row) {
                        return intval($row['value']);
                    }
                    return null;
                }

                $resultDataList = $ps->fetchAll();

                $collection = $this->createCollection($relEntity->getEntityType(), $resultDataList);
                $collection->setAsFetched();

                return $collection;

            case Entity::BELONGS_TO_PARENT:
                $foreignEntityType = $entity->get($keySet['typeKey']);
                $foreignEntityId = $entity->get($key);

                if (!$foreignEntityType || !$foreignEntityId) {
                    throw new LogicException(
                        "Bad definition for relationship {$relationName} in {$entityType} entity."
                    );
                }

                $params['whereClause'][$foreignKey] = $foreignEntityId;
                $params['offset'] = 0;
                $params['limit'] = 1;

                $relEntity = $this->entityFactory->create($foreignEntityType);

                $sql = $this->query->createSelectQuery($foreignEntityType, $params);

                $ps = $this->pdo->query($sql);

                if (!$ps) {
                    return null;
                }

                if ($returnTotalCount) {
                    foreach ($ps as $row) {
                        return intval($row['value']);
                    }
                    return 0;
                }

                foreach ($ps as $row) {
                    $relEntity = $this->fromRow($relEntity, $row);
                    return $relEntity;
                }

                return null;
        }

        throw new LogicException(
            "Bad 'type' {$relType} in definition for relationship {$relationName} in {$entityType} entity."
        );
    }

    /**
     * Get a number of related enities in DB.
     */
    public function countRelated(Entity $entity, string $relationName, ?array $params = null) : int
    {
        return (int) $this->selectRelatedInternal($entity, $relationName, $params, true);
    }

    /**
     * Relate an entity with another entity.
     */
    public function relate(Entity $entityFrom, string $relationName, Entity $entityTo, ?array $data = null) : bool
    {
        return $this->addRelation($entityFrom, $relationName, null, $entityTo, $data);
    }

    /**
     * Relate an entity from another entity.
     */
    public function unrelate(Entity $entityFrom, string $relationName, Entity $entityTo) : bool
    {
        return $this->removeRelation($entityFrom, $relationName, null, false, $entityTo);
    }

    /**
     * Update relationship columns.
     */
    public function updateRelation(Entity $entity, string $relationName, ?string $id = null, array $columnData) : bool
    {
        if (empty($id) || empty($relationName)) {
            return false;
        }

        if (empty($columnData)) return false;

        $relDefs = $entity->getRelations()[$relationName];
        $keySet = $this->query->getKeys($entity, $relationName);

        $relType = $relDefs['type'];

        switch ($relType) {
            case Entity::MANY_MANY:
                $relTable = $this->toDb($relDefs['relationName']);
                $key = $keySet['key'];
                $foreignKey = $keySet['foreignKey'];
                $nearKey = $keySet['nearKey'];
                $distantKey = $keySet['distantKey'];

                $setArr = [];
                foreach ($columnData as $column => $value) {
                    $setArr[] = "`".$this->toDb($column) . "` = " . $this->quote($value);
                }
                if (empty($setArr)) {
                    return true;
                }
                $setPart = implode(', ', $setArr);

                $wherePart =
                    $this->toDb($nearKey) . " = " . $this->pdo->quote($entity->id) . "
                    AND " . $this->toDb($distantKey) . " = " . $this->pdo->quote($id) . " AND deleted = 0";

                if (!empty($relDefs['conditions']) && is_array($relDefs['conditions'])) {
                    foreach ($relDefs['conditions'] as $f => $v) {
                        $wherePart .= " AND " . $this->toDb($f) . " = " . $this->pdo->quote($v);
                    }
                }

                $sql = $this->composeUpdateQuery($relTable, $setPart, $wherePart);

                $this->pdo->query($sql);

                return true;
        }

        return false;
    }

    /**
     * Get a relationship column value.
     *
     * @return string|int|float|bool|null A relationship column value.
     */
    public function getRelationColumn(Entity $entity, string $relationName, string $id, string $column)
    {
        $type = $entity->getRelationType($relationName);

        if (!$type === Entity::MANY_MANY) {
            throw new Error("'getRelationColumn' works only on many-to-many relations.");
        }

        $relDefs = $entity->getRelations()[$relationName];

        $relTable = $this->toDb($relDefs['relationName']);

        $keySet = $this->query->getKeys($entity, $relationName);
        $key = $keySet['key'];
        $foreignKey = $keySet['foreignKey'];
        $nearKey = $keySet['nearKey'];
        $distantKey = $keySet['distantKey'];

        $additionalColumns = $entity->getRelationParam($relationName, 'additionalColumns') ?? [];

        if (!isset($additionalColumns[$column])) return null;

        $columnType = $additionalColumns[$column]['type'] ?? 'string';

        $columnAlias = $this->query->sanitizeSelectAlias($column);

        $sql =
            "SELECT " . $this->toDb($this->query->sanitize($column)) . " AS `{$columnAlias}` FROM `{$relTable}` " .
            "WHERE ";

        $wherePart =
            $this->toDb($nearKey) . " = " . $this->pdo->quote($entity->id) . " ".
            "AND " . $this->toDb($distantKey) . " = " . $this->pdo->quote($id) . " AND deleted = 0";

        $sql .= $wherePart;

        $ps = $this->pdo->query($sql);
        if ($ps) {
            foreach ($ps as $row) {
                $value = $row[$columnAlias];
                if ($columnType == 'bool') {
                    $value = boolval($value);
                } else if ($columnType == 'int') {
                    $value = intval($value);
                } else if ($columnType == 'float') {
                    $value = floatval($value);
                }

                return $value;
            }
        }

        return null;
    }

    /**
     * Mass relate.
     */
    public function massRelate(Entity $entity, string $relationName, array $params = [])
    {
        $id = $entity->id;

        if (empty($id) || empty($relationName)) {
            throw new Error("Cant't mass relate on empty ID or relation name.");
        }

        $relDefs = $entity->getRelations()[$relationName];

        if (!isset($relDefs['entity']) || !isset($relDefs['type'])) {
            throw new LogicException("Not appropriate definition for relationship {$relationName} in " . $entity->getEntityType() . " entity.");
        }

        $relType = $relDefs['type'];

        $foreignEntityType = $relDefs['entity'];

        $relEntity = $this->entityFactory->create($foreignEntityType);

        $keySet = $this->query->getKeys($entity, $relationName);

        switch ($relType) {
            case Entity::MANY_MANY:
                $nearKey = $keySet['nearKey'];
                $distantKey = $keySet['distantKey'];

                $middleName = ucfirst($relDefs['relationName']);

                $columns = [];
                $columns[] = $nearKey;

                $valueList = [];
                $valueList[] = $entity->id;

                $conditions = $relDefs['conditions'] ?? [];

                foreach ($conditions as $left => $value) {
                    $columns[] = $left;
                    $valueList[] = $v;
                }

                $columns[] = $distantKey;

                $params['select'] = [];

                foreach ($valueList as $i => $value) {
                   $params['select'][] = ['VALUE:' . $value, 'v' . strval($i)];
                }

                $params['select'][] = 'id';

                unset($params['orderBy']);
                unset($params['order']);

                $params['from'] = $foreignEntityType;

                $sql = $this->query->createInsertQuery($middleName, [
                    'columns' => $columns,
                    'valuesSelectParams' => $params,
                    'update' => [
                        'deleted' => false,
                    ],
                ]);

                $this->runQuery($sql, true);

                return;
        }

        throw new LogicException("Relation type '{$relType}' is not supported for mass relate.");
    }

    public function runQuery(string $query, bool $rerunIfDeadlock = false)
    {
        try {
            return $this->pdo->query($query);
        } catch (Exception $e) {
            if ($rerunIfDeadlock) {
                if (isset($e->errorInfo) && $e->errorInfo[0] == 40001 && $e->errorInfo[1] == 1213) {
                    return $this->pdo->query($query);
                }
            }
            throw $e;
        }
    }

    public function addRelation(
        Entity $entity, string $relationName, ?string $id = null, ?Entity $relEntity = null, ?array $data = null
    ) : bool {
        $entityType = $entity->getEntityType();

        if (!is_null($relEntity)) {
            $id = $relEntity->id;
        }

        if (empty($id) || empty($relationName) || !$entity->get('id')) {
            throw new Error("Can't relate an empty entity or relation name.");
        }

        if (!$entity->hasRelation($relationName)) {
            throw new Error("Relation '{$relationName}' does not exist in '{$entityType}'.");
        }

        $relDefs = $entity->getRelations()[$relationName];

        $relType = $entity->getRelationType($relationName);
        $foreignEntityType = $entity->getRelationParam($relationName, 'entity');

        if (!$relType || !$foreignEntityType && $relType !== Entity::BELONGS_TO_PARENT) {
            throw new LogicException("Not appropriate definition for relationship {$relationName} in '{$entityType}' entity.");
        }

        if (is_null($relEntity)) {
            $relEntity = $this->entityFactory->create($foreignEntityType);
            $relEntity->id = $id;
        }

        $keySet = $this->query->getKeys($entity, $relationName);

        switch ($relType) {
            case Entity::BELONGS_TO:
                $key = $relationName . 'Id';

                $foreignRelationName = $entity->getRelationParam($relationName, 'foreign');

                if ($foreignRelationName && $relEntity->getRelationParam($foreignRelationName, 'type') === Entity::HAS_ONE) {
                    $sql = $this->query->createUpdateQuery($entityType, [
                        'whereClause' => [
                            'id!=' => $entity->id,
                            $key => $id,
                            'deleted' => false,
                        ],
                        'update' => [
                            $key => NULL,
                        ],
                    ]);

                    $this->runQuery($sql, true);
                }

                $sql = $this->query->createUpdateQuery($entityType, [
                    'whereClause' => [
                        'id' => $entity->id,
                        'deleted' => false,
                    ],
                    'update' => [
                        $key => $relEntity->id,
                    ],
                ]);

                $this->runQuery($sql, true);

                return true;

            case Entity::BELONGS_TO_PARENT:
                $key = $relationName . 'Id';
                $typeKey = $relationName . 'Type';

                $sql = $this->query->createUpdateQuery($entityType, [
                    'whereClause' => [
                        'id' => $entity->id,
                        'deleted' => false,
                    ],
                    'update' => [
                        $key => $relEntity->id,
                        $typeKey => $relEntity->getEntityType(),
                    ],
                ]);

                $this->runQuery($sql, true);

                return true;

            case Entity::HAS_ONE:
                $foreignKey = $keySet['foreignKey'];

                if ($this->count($relEntity, ['whereClause' => ['id' => $id]]) === 0) {
                    return false;
                }

                $sql = $this->query->createUpdateQuery($relEntity->getEntityType(), [
                    'whereClause' => [
                        $foreignKey => $entity->id,
                        'deleted' => false,
                    ],
                    'update' => [
                        $foreignKey => NULL,
                    ],
                ]);

                $this->runQuery($sql, true);

                $sql = $this->query->createUpdateQuery($relEntity->getEntityType(), [
                    'whereClause' => [
                        'id' => $id,
                        'deleted' => false,
                    ],
                    'update' => [
                        $foreignKey => $entity->id,
                    ],
                ]);

                $this->runQuery($sql, true);

                return true;

            case Entity::HAS_CHILDREN:
            case Entity::HAS_MANY:
                $key = $keySet['key'];
                $foreignKey = $keySet['foreignKey'];

                if ($this->count($relEntity, ['whereClause' => ['id' => $id]]) === 0) {
                    return false;
                }

                $set = [
                    $foreignKey => $entity->get('id'),
                ];

                if ($relType == Entity::HAS_CHILDREN) {
                    $foreignType = $keySet['foreignType'];
                    $set[$foreignType] = $entity->getEntityType();
                }

                $sql = $this->query->createUpdateQuery($relEntity->getEntityType(), [
                    'whereClause' => [
                        'id' => $id,
                        'deleted' => false,
                    ],
                    'update' => $set,
                ]);

                $this->runQuery($sql, true);

                return true;

            case Entity::MANY_MANY:
                $nearKey = $keySet['nearKey'];
                $distantKey = $keySet['distantKey'];

                if ($this->count($relEntity, ['whereClause' => ['id' => $id]]) === 0) {
                    return false;
                }

                if (!isset($relDefs['relationName'])) {
                    throw new LogicException("Bad relation '{$relationName}' in '{$entityType}'.");
                }

                $middleName = ucfirst($relDefs['relationName']);

                $conditions = $relDefs['conditions'] ?? [];
                $data = $data ?? [];

                $where = [
                    $nearKey => $entity->id,
                    $distantKey => $relEntity->id,
                ];

                foreach ($conditions as $f => $v) {
                    $where[$f] = $v;
                }

                $sql = $this->query->createSelectQuery($middleName, [
                    'select' => ['id'],
                    'whereClause' => $where,
                    'withDeleted' => true,
                ]);

                $ps = $this->pdo->query($sql);

                // @todo Leave one INSERT for better performance.

                if ($ps->rowCount() == 0) {
                    $values = $where;
                    $columns = array_keys($values);

                    $update = [
                        'deleted' => false,
                    ];

                    foreach ($data as $column => $value) {
                        $columns[] = $column;
                        $values[$column] = $value;
                        $update[$column] = $value;
                    }

                    $sql = $this->query->createInsertQuery($middleName, [
                        'columns' => $columns,
                        'values' => $values,
                        'update' => $update,
                    ]);

                    $this->runQuery($sql, true);

                    return true;
                }

                $update = [
                    'deleted' => false,
                ];

                foreach ($data as $column => $value) {
                    $update[$column] = $value;
                }

                $sql = $this->query->createUpdateQuery($middleName, [
                    'whereClause' => $where,
                    'update' => $update,
                ]);

                $this->runQuery($sql, true);

                return true;

        }

        throw new LogicException("Relation type '{$relType}' is not supported.");
    }

    public function removeRelation(
        Entity $entity, string $relationName, ?string $id = null, bool $all = false, ?Entity $relEntity = null
    ) : bool {
        if (!is_null($relEntity)) {
            $id = $relEntity->id;
        }

        if (empty($id) && empty($all) || empty($relationName)) {
            return false;
        }

        if (!$entity->hasRelation($relationName)) return false;

        $relDefs = $entity->getRelations()[$relationName];

        $relType = $entity->getRelationType($relationName);
        $foreignEntityType = $entity->getRelationParam($relationName, 'entity');

        if (!$relType || !$foreignEntityType && $relType !== Entity::BELONGS_TO_PARENT) {
            throw new LogicException(
                "Not appropriate definition for relationship {$relationName} in " . $entity->getEntityType() . " entity."
            );
        }

        if (is_null($relEntity)) {
            $relEntity = $this->entityFactory->create($foreignEntityType);
            if (!$relEntity) {
                return false;
            }
            $relEntity->id = $id;
        }

        $keySet = $this->query->getKeys($entity, $relationName);

        switch ($relType) {
            case Entity::BELONGS_TO:
                $key = $relationName . 'Id';
                $setPart = $this->toDb($key) . " = " . $this->quote(null);
                $wherePart = $this->query->buildWherePart($entity->getEntityType(), ['id' => $entity->id, 'deleted' => 0]);

                $entity->set([
                    $key => null
                ]);

                $sql = $this->composeUpdateQuery(
                    $this->toDb($entity->getEntityType()),
                    $setPart,
                    $wherePart
                );

                $this->pdo->query($sql);

                return true;

            case Entity::BELONGS_TO_PARENT:
                $key = $relationName . 'Id';
                $typeKey = $relationName . 'Type';

                $entity->set([
                    $key => null,
                    $typeKey => null
                ]);

                $setPart =
                    $this->toDb($key) . " = " . $this->quote(null) . ', ' .
                    $this->toDb($typeKey) . " = " . $this->quote(null);
                $wherePart = $this->query->buildWherePart($entity->getEntityType(), ['id' => $entity->id, 'deleted' => 0]);

                $sql = $this->composeUpdateQuery(
                    $this->toDb($entity->getEntityType()),
                    $setPart,
                    $wherePart
                );

                $this->pdo->query($sql);

                return true;

            case Entity::HAS_ONE:
            case Entity::HAS_MANY:
            case Entity::HAS_CHILDREN:
                $key = $keySet['key'];
                $foreignKey = $keySet['foreignKey'];

                $setPart = $this->toDb($foreignKey) . " = " . "NULL";

                $whereClause = ['deleted' => false];
                if (empty($all) && $relType != Entity::HAS_ONE) {
                    $whereClause['id'] = $id;
                } else {
                    $whereClause[$foreignKey] = $entity->id;
                }

                if ($relType == Entity::HAS_CHILDREN) {
                    $foreignType = $keySet['foreignType'];
                    $whereClause[$foreignType] = $entity->getEntityType();
                }

                $wherePart = $this->query->buildWherePart($relEntity->getEntityType(), $whereClause);
                $sql = $this->composeUpdateQuery($this->toDb($relEntity->getEntityType()), $setPart, $wherePart);

                $this->pdo->query($sql);

                return true;

            case Entity::MANY_MANY:
                $key = $keySet['key'];
                $foreignKey = $keySet['foreignKey'];
                $nearKey = $keySet['nearKey'];
                $distantKey = $keySet['distantKey'];

                $relTable = $this->toDb($relDefs['relationName']);

                $setPart = 'deleted = 1';
                $wherePart = $this->toDb($nearKey) . " = " . $this->pdo->quote($entity->id);

                if (empty($all)) {
                    $wherePart .= " AND " . $this->toDb($distantKey) . " = " . $this->pdo->quote($id) . "";
                }

                if (!empty($relDefs['conditions']) && is_array($relDefs['conditions'])) {
                    foreach ($relDefs['conditions'] as $f => $v) {
                        $wherePart .= " AND " . $this->toDb($f) . " = " . $this->pdo->quote($v);
                    }
                }

                $sql = $this->composeUpdateQuery($relTable, $setPart, $wherePart);

                $this->pdo->query($sql);

                return true;
        }

        return false;
    }

    public function removeAllRelations(Entity $entity, string $relationName) : bool
    {
        return $this->removeRelation($entity, $relationName, null, true);
    }

    protected function quote($value)
    {
        if (is_null($value)) {
            return 'NULL';
        } else if (is_bool($value)) {
            return $value ? '1' : '0';
        } else {
            return $this->pdo->quote($value);
        }
    }

    /**
     * Insert an entity into DB.
     *
     * @todo Set 'id' if autoincrement (as fetched).
     */
    public function insert(Entity $entity)
    {
        $this->insertInternal($entity);
    }

    /**
     * Insert an entity into DB, on duplicate key update specified attributes.
     */
    public function insertOnDuplicateUpdate(Entity $entity, array $onDuplicateUpdateAttributeList)
    {
        $this->insertInternal($entity, $onDuplicateUpdateAttributeList);
    }

    protected function insertInternal(Entity $entity, ?array $onDuplicateUpdateAttributeList = null)
    {
        $update = null;

        if ($onDuplicateUpdateAttributeList && count($onDuplicateUpdateAttributeList)) {
            $update = $onDuplicateSetMap = $this->getInsertOnDuplicateSetMap($entity, $onDuplicateUpdateAttributeList);
        }

        $sql = $this->query->createInsertQuery($entity->getEntityType(), [
            'columns' => $this->getInsertColumnList($entity),
            'values' => $this->getInsertValueMap($entity),
            'update' => $update,
        ]);

        $this->runQuery($sql, true);
    }

    /**
     * Mass insert collection into DB.
     */
    public function massInsert(Collection $collection)
    {
        if (!count($collection)) {
            return;
        }

        $values = [];

        foreach ($collection as $entity) {
            $values[] = $this->getInsertValueMap($entity);
        }

        $sql = $this->query->createInsertQuery($entity->getEntityType(), [
            'columns' => $this->getInsertColumnList($collection[0]),
            'values' => $values,
        ]);

        $this->runQuery($sql, true);
    }

    protected function getInsertColumnList(Entity $entity) : array
    {
        $columnList = [];

        $dataList = $this->toValueMap($entity);

        foreach ($dataList as $attribute => $value) {
            $columnList[] = $attribute;
        }

        return $columnList;
    }

    protected function getInsertValueMap(Entity $entity) : array
    {
        $map = [];

        foreach ($this->toValueMap($entity) as $attribute => $value) {
            $type = $entity->getAttributeType($attribute);
            $map[$attribute] = $this->prepareValueForInsert($type, $value);
        }

        return $map;
    }

    protected function getInsertOnDuplicateSetMap(Entity $entity, array $attributeList)
    {
        $list = [];

        foreach ($attributeList as $a) {
            $list[$a] = $this->prepareValueForInsert($entity, $entity->get($a));
        }

        return $list;
    }

    protected function getValueMapForUpdate(Entity $entity) : array
    {
        $valueMap = [];

        foreach ($this->toValueMap($entity) as $attribute => $value) {
            if ($attribute == 'id') {
                continue;
            }

            $type = $entity->getAttributeType($attribute);

            if ($type == Entity::FOREIGN) {
                continue;
            }

            if (!$entity->isAttributeChanged($attribute)) {
                continue;
            }

            $valueMap[$attribute] = $this->prepareValueForInsert($type, $value);
        }

        return $valueMap;
    }

    /**
     * Update an entity in DB.
     */
    public function update(Entity $entity)
    {
        $valueMap = $this->getValueMapForUpdate($entity);

        if (count($valueMap) == 0) {
            return;
        }

        $sql = $this->query->createUpdateQuery($entity->getEntityType(), [
            'whereClause' => [
                'id' => $entity->id,
                'deleted' => false,
            ],
            'update' => $valueMap,
        ]);

        $this->pdo->query($sql);
    }

    protected function prepareValueForInsert($type, $value)
    {
        if ($type == Entity::JSON_ARRAY && is_array($value)) {
            $value = json_encode($value, \JSON_UNESCAPED_UNICODE);
        } else if ($type == Entity::JSON_OBJECT && (is_array($value) || $value instanceof \StdClass)) {
            $value = json_encode($value, \JSON_UNESCAPED_UNICODE);
        } else {
            if (is_array($value) || is_object($value)) {
                return null;
            }
        }

        return $value;
    }

    /**
     * Delete an entity from DB.
     */
    public function deleteFromDb(string $entityType, string $id, bool $onlyDeleted = false)
    {
        if (empty($entityType) || empty($id)) {
            throw new Error("Can't delete an empty entity type or ID from DB.");
        }

        $whereClause = [
            'id' => $id,
        ];

        if ($onlyDeleted) {
            $whereClause['deleted'] = true;
        }

        $sql = $this->query->createDeleteQuery($entityType, [
            'whereClause' => $whereClause,
        ]);

        $this->runQuery($sql);
    }

    /**
     * Unmark an entity as deleted in DB.
     */
    public function restoreDeleted(string $entityType, string $id)
    {
        if (empty($entityType) || empty($id)) {
            throw new Error("Can't restore an empty entity type or ID.");
        }

        $whereClause = [
            'id' => $id,
        ];

        $sql = $this->query->createUpdateQuery($entityType, [
            'whereClause' => $whereClause,
            'update' => ['deleted' => false],
        ]);

        $this->runQuery($sql);
    }

    /**
     * Mark an entity as deleted in DB.
     */
    public function delete(Entity $entity) : bool
    {
        $entity->set('deleted', true);

        return (booL) $this->update($entity);
    }

    protected function toValueMap(Entity $entity, bool $onlyStorable = true) : array
    {
        $data = [];

        foreach ($entity->getAttributes() as $attribute => $defs) {
            if ($entity->has($attribute)) {
                if ($onlyStorable) {
                    if (
                        !empty($defs['notStorable'])
                        ||
                        !empty($defs['autoincrement'])
                        ||
                        isset($defs['source']) && $defs['source'] != 'db'
                    ) continue;
                    if ($defs['type'] == Entity::FOREIGN) continue;
                }
                $data[$attribute] = $entity->get($attribute);
            }
        }

        return $data;
    }

    protected function fromRow(Entity $entity, $data) : Entity
    {
        $entity->set($data);

        return $entity;
    }

    protected function getManyManyJoin(Entity $entity, string $relationName, ?array $conditions = null) : array
    {
        $defs = $entity->getRelations()[$relationName];

        $middleName = $defs['relationName'] ?? null;

        $keySet = $this->query->getKeys($entity, $relationName);

        $key = $keySet['key'];
        $foreignKey = $keySet['foreignKey'];
        $nearKey = $keySet['nearKey'];
        $distantKey = $keySet['distantKey'];

        if (!$middleName) {
            throw new Error("No 'relationName' parameter for '{$relationName}' relationship.");
        }

        $alias = lcfirst($middleName);

        $join = [
            ucfirst($middleName),
            $alias,
            [
                "{$distantKey}:" => $foreignKey,
                "{$nearKey}" => $entity->get($key),
                "deleted" => false,
            ],
        ];

        $conditions = $conditions ?? [];
        if (!empty($defs['conditions']) && is_array($defs['conditions'])) {
            $conditions = array_merge($conditions, $defs['conditions']);
        }

        $join[2] = array_merge($join[2], $conditions);

        return $join;
    }

    protected function composeInsertQuery(string $table, string $fields, $values, ?string $onDuplicate = null) : string
    {
        $sql = "INSERT INTO `{$table}`";
        $sql .= " ({$fields})";
        if (!is_array($values)) {
            $sql .= " VALUES ({$values})";
        } else {
            $sql .= " VALUES (" . implode("), (", $values) . ")";
        }

        if ($onDuplicate) {
            $sql .= " ON DUPLICATE KEY UPDATE " . $onDuplicate;
        }

        return $sql;
    }

    protected function composeUpdateQuery(string $table, string $set, string $where)
    {
        $sql = "UPDATE `{$table}` SET {$set} WHERE {$where}";

        return $sql;
    }

    protected function toDb(string $attribute)
    {
        return $this->query->toDb($attribute);
    }
}
