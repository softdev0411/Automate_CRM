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

namespace Espo\Core\Select\Where;

use Espo\Core\{
    Utils\Metadata,
    InjectableFactory,
};

use Espo\{
    Entities\User,
};

class ConverterFactory
{
    protected $injectableFactory;
    protected $metadata;

    public function __construct(InjectableFactory $injectableFactory, Metadata $metadata)
    {
        $this->injectableFactory = $injectableFactory;
        $this->metadata = $metadata;
    }

    public function create(string $entityType, User $user) : Converter
    {
        $dateTimeItemTransformerClassName = $this->getDateTimeItemTransformerClassName($entityType);

        $dateTimeItemTransformer = $this->injectableFactory->createWith($dateTimeItemTransformerClassName, [
            'entityType' => $entityType,
            'user' => $user,
        ]);

        $itemConverterClassName = $this->getItemConverterClassName($entityType);

        $itemConverter = $this->injectableFactory->createWith($itemConverterClassName, [
            'entityType' => $entityType,
            'user' => $user,
            'dateTimeItemTransformer' => $dateTimeItemTransformer,
        ]);

        $converterClassName = $this->getConverterClassName($entityType);

        return $this->injectableFactory->createWith($converterClassName, [
            'entityType' => $entityType,
            'user' => $user,
            'itemConverter' => $itemConverter,
        ]);
    }

    protected function getConverterClassName(string $entityType) : string
    {
        $className = $this->metadata->get(['selectDefs', $entityType, 'whereConverterClassName']);

        if ($className) {
            return $className;
        }

        return Converter::class;
    }

    protected function getItemConverterClassName(string $entityType) : string
    {
        $className = $this->metadata->get(['selectDefs', $entityType, 'whereItemConverterClassName']);

        if ($className) {
            return $className;
        }

        return ItemGeneralConverter::class;
    }

    protected function getDateTimeItemTransformerClassName(string $entityType) : string
    {
        $className = $this->metadata->get(['selectDefs', $entityType, 'whereDateTimeItemTransformerClassName']);

        if ($className) {
            return $className;
        }

        return DateTimeItemTransformer::class;
    }
}
