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

namespace tests\unit\Espo\Core\Select\AccessControl;

use Espo\Core\{
    Select\AccessControl\FilterFactory,
    Select\AccessControlFilters\OnlyOwn,
    Select\Helpers\FieldHelper,
    Utils\Metadata,
    InjectableFactory,
};

use Espo\{
    Entities\User,
};

class FilterFactoryTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp() : void
    {
        $this->injectableFactory = $this->createMock(InjectableFactory::class);
        $this->metadata = $this->createMock(Metadata::class);
        $this->user = $this->createMock(User::class);
        $this->fieldHelper = $this->createMock(FieldHelper::class);

        $this->factory = new FilterFactory(
            $this->injectableFactory,
            $this->metadata
        );
    }

    public function testCreate1()
    {
        $this->prepareFactoryTest(null, OnlyOwn::class, 'onlyOwn');
    }

    public function testCreate2()
    {
        $this->prepareFactoryTest('SomeClass', OnlyOwn::class, 'onlyOwn');
    }

    protected function prepareFactoryTest(?string $className, string $defaultClassName, string $name)
    {
        $entityType = 'Test';

        $object = $this->createMock($defaultClassName);

        $this->metadata
            ->expects($this->any())
            ->method('get')
            ->willReturnMap([
                [['selectDefs', $entityType, 'accessControlFilterClassNameMap', $name], null, $className],
            ]);

        $className = $className ?? $defaultClassName;

        $object = $this->createMock($defaultClassName);

        $this->injectableFactory
            ->expects($this->exactly(2))
            ->method('createWith')
            ->willReturnMap([
                [
                    FieldHelper::class,
                    [
                        'entityType' => $entityType,
                    ],
                    $this->fieldHelper,
                ],
                [
                    $className,
                    [
                        'entityType' => $entityType,
                        'user' => $this->user,
                        'fieldHelper' => $this->fieldHelper,
                    ],
                    $object,
                ],
            ]);

        $resultObject = $this->factory->create(
            $entityType,
            $this->user,
            $name
        );

        $this->assertEquals($object, $resultObject);

        $this->assertTrue(
            $this->factory->has($entityType, $name)
        );

        $this->metadata
            ->expects($this->any())
            ->method('get')
            ->willReturnMap([
                [['selectDefs', $entityType, 'accessControlFilterClassNameMap', 'badName'], null, null],
            ]);

        $this->assertFalse(
            $this->factory->has($entityType, 'badName')
        );
    }
}
