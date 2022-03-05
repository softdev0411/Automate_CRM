<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2022 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
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

namespace Espo\Core\Record\Duplicator;

use Espo\ORM\Defs;
use Espo\Core\Utils\Metadata;
use Espo\Core\InjectableFactory;

use RuntimeException;

class FieldDuplicatorFactory
{
    private Defs $defs;

    private Metadata $metadata;

    private InjectableFactory $injectableFactory;

    public function __construct(Defs $defs, Metadata $metadata, InjectableFactory $injectableFactory)
    {
        $this->defs = $defs;
        $this->metadata = $metadata;
        $this->injectableFactory = $injectableFactory;
    }

    public function create(string $entityType, string $field): FieldDuplicator
    {
        $className = $this->getClassName($entityType, $field);

        if (!$className) {
            throw new RuntimeException("No field duplicator for the field.");
        }

        return $this->injectableFactory->create($className);
    }

    public function has(string $entityType, string $field): bool
    {
        return $this->getClassName($entityType, $field) !== null;
    }

    /**
     * @return ?class-string
     */
    private function getClassName(string $entityType, string $field): ?string
    {
        $fieldDefs = $this->defs
            ->getEntity($entityType)
            ->getField($field);

        $className1 = $fieldDefs->getParam('duplicatorClassName');

        if ($className1) {
            return $className1;
        }

        $type = $fieldDefs->getType();

        $className2 = $this->metadata->get(['fields', $type, 'duplicatorClassName']);

        if ($className2) {
            return $className2;
        }

        return null;
    }
}
