<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2021 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
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

namespace Espo\ORM;

use const E_USER_DEPRECATED;

use StdClass;
use InvalidArgumentException;

class BaseEntity implements Entity
{
    public $id = null;

    protected $entityType;

    private $isNotNew = false;

    private $isSaved = false;

    protected $isFetched = false;

    protected $isBeingSaved = false;

    /**
     * @todo Make protected. Rename to `attributes`.
     * @deprecated
     */
    public $fields = [];

    protected $relations = [];

    /**
     * A field-value map.
     */
    protected $valuesContainer = [];

    /**
     * A field-value map of values fetched from DB (before changed).
     */
    protected $fetchedValuesContainer = [];

    /**
     * @var ?EntityManager
     */
    protected $entityManager;

    public function __construct(string $entityType, array $defs = [], ?EntityManager $entityManager = null)
    {
        $this->entityType = $entityType ?? null;

        $this->entityManager = $entityManager;

        $this->fields = $defs['attributes'] ?? $defs['fields'] ?? $this->fields;
        $this->relations = $defs['relations'] ?? $this->relations;
    }

    public function getId() : ?string
    {
        return $this->get('id');
    }

    public function clear(string $name) : void
    {
        unset($this->valuesContainer[$name]);
    }

    public function reset() : void
    {
        $this->valuesContainer = [];
    }

    protected function setValue($name, $value) : void
    {
        $this->valuesContainer[$name] = $value;
    }

    public function set($p1, $p2 = null) : void
    {
        if (is_array($p1) || is_object($p1)) {
            if (is_object($p1)) {
                $p1 = get_object_vars($p1);
            }

            if ($p2 === null) {
                $p2 = false;
            }

            if ($p2) {
                trigger_error(
                    'Second parameter is deprecated in Entity::set(array, onlyAccessible).',
                    E_USER_DEPRECATED
                );
            }

            $this->populateFromArray($p1, $p2);

            return;
        }

        if (is_string($p1)) {
            $name = $p1;
            $value = $p2;

            if ($name == 'id') {
                $this->id = $value;
            }

            if (!$this->hasAttribute($name)) {
                return;
            }

            $method = '_set' . ucfirst($name);

            if (method_exists($this, $method)) {
                $this->$method($value);

                return;
            }

            $this->populateFromArray([
                $name => $value,
            ]);

            return;
        }

        throw new InvalidArgumentException();
    }

    /**
     * @param $params @deprecated
     *
     * @retrun ?mixed
     */
    public function get(string $name, $params = [])
    {
        if ($name === 'id') {
            return $this->id;
        }

        $method = '_get' . ucfirst($name);

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        if ($this->hasAttribute($name) && isset($this->valuesContainer[$name])) {
            return $this->valuesContainer[$name];
        }

        if (!empty($params)) {
            trigger_error(
                'Second parameter will be removed from the method Entity::get.',
                E_USER_DEPRECATED
            );
        }

        // @todo Remove this.
        if ($this->hasRelation($name) && $this->id && $this->entityManager) {
            trigger_error(
                "Accessing related records with Entity::get is deprecated. " .
                "Use \$repository->getRelation(...)->find()",
                E_USER_DEPRECATED
            );

            return $this->entityManager
                ->getRepository($this->getEntityType())
                ->findRelated($this, $name, $params);
        }

        return null;
    }

    public function has(string $name) : bool
    {
        if ($name == 'id') {
            return (bool) $this->id;
        }

        $method = '_has' . ucfirst($name);

        if (method_exists($this, $method)) {
            return (bool) $this->$method();
        }

        if (array_key_exists($name, $this->valuesContainer)) {
            return true;
        }

        return false;
    }

    /**
     * @deprecated
     * @todo Make protected.
     */
    public function populateFromArray(array $data, bool $onlyAccessible = true, bool $reset = false) : void
    {
        if ($reset) {
            $this->reset();
        }

        foreach ($this->getAttributeList() as $attribute) {
            if (!array_key_exists($attribute, $data)) {
                continue;
            }

            if ($attribute == 'id') {
                $this->id = $data[$attribute];

                continue;
            }

            if ($onlyAccessible && $this->getAttributeParam($attribute, 'notAccessible')) {
                continue;
            }

            $value = $data[$attribute];

            $this->populateFromArrayItem($attribute, $value);
        }
    }

    protected function populateFromArrayItem(string $attribute, $value) : void
    {
        $preparedValue = $this->preparePopulateFromArrayItemValue($attribute, $value);

        $method = '_set' . ucfirst($attribute);

        if (method_exists($this, $method)) {
            $this->$method($preparedValue);

            return;
        }

        $this->valuesContainer[$attribute] = $preparedValue;
    }

    /**
     * @return ?mixed
     */
    protected function preparePopulateFromArrayItemValue(string $attribute, $value)
    {
        if (is_null($value)) {
            return $value;
        }

        $attributeType = $this->getAttributeType($attribute);

        if ($attributeType === self::FOREIGN) {
            $attributeType = $this->getForeignAttributeType($attribute) ?? $attributeType;
        }

        switch ($attributeType) {
            case self::VARCHAR:
                break;

            case self::BOOL:
                return ($value === true || $value === 'true' || $value === '1');

            case self::INT:
                return intval($value);

            case self::FLOAT:
                return floatval($value);

            case self::JSON_ARRAY:
                $preparedValue = is_string($value) ? json_decode($value) : $value;

                if (!is_array($preparedValue)) {
                    $value = null;
                }

                return $preparedValue;

            case self::JSON_OBJECT:
                $preparedValue = is_string($value) ? json_decode($value) : $value;

                if (!($preparedValue instanceof StdClass) && !is_array($preparedValue)) {
                    $preparedValue = null;
                }

                return $preparedValue;

            default:
                break;
        }

        return $value;
    }

    private function getForeignAttributeType(string $attribute) : ?string
    {
        if (!$this->entityManager) {
            return null;
        }

        $defs = $this->entityManager->getDefs();

        $entityDefs = $defs->getEntity($this->entityType);

        $relation = $entityDefs->getAttribute($attribute)->getParam('relation');
        $foreign = $entityDefs->getAttribute($attribute)->getParam('foreign');

        if (!$relation) {
            return null;
        }

        if (!$foreign) {
            return null;
        }

        if (!is_string($foreign)) {
            return self::VARCHAR;
        }

        if (!$entityDefs->getRelation($relation)->hasForeignEntityType()) {
            return null;
        }

        $entityType = $entityDefs->getRelation($relation)->getForeignEntityType();

        if (!$defs->hasEntity($entityType)) {
            return null;
        }

        $foreignEntityDefs = $defs->getEntity($entityType);

        if (!$foreignEntityDefs->hasAttribute($foreign)) {
            return null;
        }

        return $foreignEntityDefs->getAttribute($foreign)->getType();
    }

    /**
     * Whether an entity is new.
     */
    public function isNew() : bool
    {
        return !$this->isNotNew;
    }

    /**
     * Set as not new. Meaning an entity is fetched or already saved.
     */
    public function setAsNotNew() : void
    {
        $this->isNotNew = true;
    }

    /**
     * Whether an entity has been saved. An entity can be already saved but not yet set as not-new.
     * To prevent inserting second time if save is called in an after-save hook.
     */
    public function isSaved() : bool
    {
        return $this->isSaved;
    }

    /**
     * Set as saved.
     */
    public function setAsSaved() : void
    {
        $this->isSaved = true;
    }

    /**
     * @deprecated
     */
    public function getEntityName()
    {
        return $this->getEntityType();
    }

    /**
     * Get an entity type.
     */
    public function getEntityType() : string
    {
        return $this->entityType;
    }

    /**
     * @deprecated
     */
    public function hasField($fieldName)
    {
        return isset($this->fields[$fieldName]);
    }

    /**
     * Whether an entity type has an attribute defined.
     */
    public function hasAttribute(string $name) : bool
    {
        return isset($this->fields[$name]);
    }

    /**
     * Whether an entity type has a relation defined.
     */
    public function hasRelation(string $relationName) : bool
    {
        return isset($this->relations[$relationName]);
    }

    /**
     * Get attribute list defined for an entity type.
     */
    public function getAttributeList() : array
    {
        return array_keys($this->getAttributes());
    }

    /**
     * Get relation list defined for an entity type.
     */
    public function getRelationList() : array
    {
        return array_keys($this->getRelations());
    }

    /**
     * @deprecated
     */
    public function getValues()
    {
        return $this->toArray();
    }

    /**
     * @deprecated
     * @todo Make protected.
     */
    public function toArray()
    {
        $arr = [];

        if (isset($this->id)) {
            $arr['id'] = $this->id;
        }

        foreach ($this->getAttributeList() as $attribute) {
            if ($attribute === 'id') {
                continue;
            }

            if ($this->has($attribute)) {
                $arr[$attribute] = $this->get($attribute);
            }
        }

        return $arr;
    }

    /**
     * Get values.
     */
    public function getValueMap() : StdClass
    {
        $array = $this->toArray();

        return (object) $array;
    }

    /**
     * @deprecated
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @deprecated
     */
    public function getAttributes()
    {
        return $this->fields;
    }

    /**
     * @deprecated
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Get an attribute type.
     */
    public function getAttributeType(string $attribute) : ?string
    {
        if (isset($this->fields[$attribute]) && isset($this->fields[$attribute]['type'])) {
            return $this->fields[$attribute]['type'];
        }

        return null;
    }

    /**
     * Get a relation type.
     */
    public function getRelationType(string $relation) : ?string
    {
        if (isset($this->relations[$relation]) && isset($this->relations[$relation]['type'])) {
            return $this->relations[$relation]['type'];
        }

        return null;
    }

    /**
     * Get an attribute parameter.
     */
    public function getAttributeParam(string $attribute, string $name)
    {
        if (isset($this->fields[$attribute]) && isset($this->fields[$attribute][$name])) {
            return $this->fields[$attribute][$name];
        }

        return null;
    }

    /**
     * Get a relation parameter.
     */
    public function getRelationParam(string $relation, string $name)
    {
        if (isset($this->relations[$relation]) && isset($this->relations[$relation][$name])) {
            return $this->relations[$relation][$name];
        }

        return null;
    }

    /**
     * Whether is fetched from DB.
     */
    public function isFetched() : bool
    {
        return $this->isFetched;
    }

    /**
     * @deprecated
     */
    public function isFieldChanged($name)
    {
        return $this->has($name) && ($this->get($name) != $this->getFetched($name));
    }

    /**
     * Whether an attribute was changed (since syncing with DB).
     */
    public function isAttributeChanged(string $name) : bool
    {
        if (!$this->has($name)) {
            return false;
        }

        if (!$this->hasFetched($name)) {
            return true;
        }

        return !self::areValuesEqual(
            $this->getAttributeType($name),
            $this->get($name),
            $this->getFetched($name),
            $this->getAttributeParam($name, 'isUnordered') ?? false
        );
    }

    protected static function areValuesEqual(string $type, $v1, $v2, bool $isUnordered = false) : bool
    {
        if ($type === self::JSON_ARRAY) {
            if (is_array($v1) && is_array($v2)) {
                if ($isUnordered) {
                    sort($v1);
                    sort($v2);
                }

                if ($v1 != $v2) {
                    return false;
                }

                foreach ($v1 as $i => $itemValue) {
                    if (is_object($v1[$i]) && is_object($v2[$i])) {
                        if (!self::areValuesEqual(self::JSON_OBJECT, $v1[$i], $v2[$i])) {
                            return false;
                        }
                        continue;
                    }

                    if ($v1[$i] !== $v2[$i]) {
                        return false;
                    }
                }

                return true;
            }
        }
        else if ($type === self::JSON_OBJECT) {
            if (is_object($v1) && is_object($v2)) {
                if ($v1 != $v2) {
                    return false;
                }

                $a1 = get_object_vars($v1);
                $a2 = get_object_vars($v2);

                foreach ($v1 as $key => $itemValue) {
                    if (is_object($a1[$key]) && is_object($a2[$key])) {
                        if (!self::areValuesEqual(self::JSON_OBJECT, $a1[$key], $a2[$key])) {
                            return false;
                        }

                        continue;
                    }

                    if (is_array($a1[$key]) && is_array($a2[$key])) {
                        if (!self::areValuesEqual(self::JSON_ARRAY, $a1[$key], $a2[$key])) {
                            return false;
                        }

                        continue;
                    }

                    if ($a1[$key] !== $a2[$key]) {
                        return false;
                    }
                }

                return true;
            }
        }

        return $v1 === $v2;
    }

    /**
     * Set a fetched value for a specific attribute.
     */
    public function setFetched(string $name, $value) : void
    {
        if ($value) {
            $type = $this->getAttributeType($name);

            if ($type === self::JSON_OBJECT) {
                $value = self::cloneObject($value);
            }
            else if ($type === self::JSON_ARRAY) {
                $value = self::cloneArray($value);
            }
        }

        $this->fetchedValuesContainer[$name] = $value;
    }

    /**
     * Get a fetched value of a specific attribute.
     *
     * @return ?mixed
     */
    public function getFetched(string $name)
    {
        if ($name === 'id') {
            return $this->id;
        }

        if (isset($this->fetchedValuesContainer[$name])) {
            return $this->fetchedValuesContainer[$name];
        }

        return null;
    }

    /**
     * Whether a fetched value is set for a specific attribute.
     */
    public function hasFetched(string $name) : bool
    {
        if ($name === 'id') {
            return !is_null($this->id);
        }

        return array_key_exists($name, $this->fetchedValuesContainer);
    }

    /**
     * Clear all set fetched values.
     */
    public function resetFetchedValues() : void
    {
        $this->fetchedValuesContainer = [];
    }

    /**
     * Copy all current values to fetched values. All current attribute values will beset as those
     * that are fetched from DB.
     */
    public function updateFetchedValues() : void
    {
        $this->fetchedValuesContainer = $this->valuesContainer;

        foreach ($this->fetchedValuesContainer as $attribute => $value) {
            $this->setFetched($attribute, $value);
        }
    }

    /**
     * Set an entity as fetched. All current attribute values will be set as those that are fetched
     * from DB.
     */
    public function setAsFetched() : void
    {
        $this->isFetched = true;

        $this->setAsNotNew();

        $this->updateFetchedValues();
    }

    /**
     * Whether an entity is being saved.
     */
    public function isBeingSaved() : bool
    {
        return $this->isBeingSaved;
    }

    public function setAsBeingSaved() : void
    {
        $this->isBeingSaved = true;
    }

    public function setAsNotBeingSaved() : void
    {
        $this->isBeingSaved = false;
    }

    /**
     * Set defined default values.
     */
    public function populateDefaults() : void
    {
        foreach ($this->fields as $field => $defs) {
            if (array_key_exists('default', $defs)) {
                $this->valuesContainer[$field] = $defs['default'];
            }
        }
    }

    protected function getEntityManager() : EntityManager
    {
        return $this->entityManager;
    }

    protected function cloneArray($value)
    {
        if (is_array($value)) {
            $copy = [];

            foreach ($value as $v) {
                if (is_object($v)) {
                    $v = clone $v;
                }

                $copy[] = $v;
            }

            return $copy;
        }

        return $value;
    }

    protected function cloneObject($value)
    {
        if (is_array($value)) {
            $copy = [];

            foreach ($value as $v) {
                $copy[] = self::cloneObject($v);
            }

            return $copy;
        }

        if (is_object($value)) {
            $copy = (object) [];

            foreach (get_object_vars($value) as $k => $v) {
                $key = $k;

                if (!is_string($key)) {
                    $key = strval($key);
                }

                $copy->$key = self::cloneObject($v);
            }

            return $copy;
        }

        return $value;
    }
}
