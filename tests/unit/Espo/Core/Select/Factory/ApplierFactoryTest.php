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

namespace tests\unit\Espo\Core\Select\Factory;

use Espo\Core\{
    Select\Factory\ApplierFactory,
    Select\SelectManagerFactory,
    Select\SelectManager,
    Select\Appliers\SelectApplier,
    Select\Appliers\BoolFilterListApplier,
    Select\Appliers\TextFilterApplier,
    Select\Appliers\WhereApplier,
    Select\Appliers\LimitApplier,
    Select\Appliers\OrderApplier,
    Select\Appliers\AdditionalApplier,
    Select\Appliers\PrimaryFilterApplier,
    Select\Appliers\AccessControlFilterApplier,
    Utils\Metadata,
    InjectableFactory,
};

use Espo\{
    Entities\User,
};

class ApplierFactoryTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp() : void
    {
        $this->injectableFactory = $this->createMock(InjectableFactory::class);
        $this->metadata = $this->createMock(Metadata::class);
        $this->selectManagerFactory = $this->createMock(SelectManagerFactory::class);
        $this->user = $this->createMock(User::class);

        $this->selectManager = $this->createMock(SelectManager::class);

        $this->factory = new ApplierFactory(
            $this->injectableFactory,
            $this->metadata,
            $this->selectManagerFactory
        );
    }

    public function testCreate1()
    {
        $this->prepareFactoryTest(null, SelectApplier::class, ApplierFactory::SELECT);
    }

    public function testCreate2()
    {
        $this->prepareFactoryTest('SomeClass', BoolFilterListApplier::class, ApplierFactory::BOOL_FILTER_LIST);
    }

    public function testCreate3()
    {
        $this->prepareFactoryTest(null, TextFilterApplier::class, ApplierFactory::TEXT_FILTER);
    }

    public function testCreate4()
    {
        $this->prepareFactoryTest(null, WhereApplier::class, ApplierFactory::WHERE);
    }

    public function testCreate5()
    {
        $this->prepareFactoryTest(null, OrderApplier::class, ApplierFactory::ORDER);
    }

    public function testCreate6()
    {
        $this->prepareFactoryTest(null, LimitApplier::class, ApplierFactory::LIMIT);
    }

    public function testCreate7()
    {
        $this->prepareFactoryTest(null, AdditionalApplier::class, ApplierFactory::ADDITIONAL);
    }

    public function testCreate8()
    {
        $this->prepareFactoryTest(null, PrimaryFilterApplier::class, ApplierFactory::PRIMARY_FILTER);
    }

    public function testCreate9()
    {
        $this->prepareFactoryTest(null, AccessControlFilterApplier::class, ApplierFactory::ACCESS_CONTROL_FILTER);
    }

    protected function prepareFactoryTest(?string $className, string $defaultClassName, string $type)
    {
        $entityType = 'Test';

        $this->selectManagerFactory
            ->expects($this->once())
            ->method('create')
            ->with('Test', $this->user)
            ->willReturn($this->selectManager);

        $this->metadata
            ->expects($this->once())
            ->method('get')
            ->with(['selectDefs', $entityType, 'applierClassNameMap', $type])
            ->willReturn($className);

        $applierClassName = $className ?? $defaultClassName;

        $applier = $this->createMock($defaultClassName);

        $with = [
            'entityType' => $entityType,
            'user' => $this->user,
            'selectManager' => $this->selectManager,
        ];

        $this->injectableFactory
            ->expects($this->once())
            ->method('createWith')
            ->with($applierClassName, $with)
            ->willReturn($applier);

        $resultApplier = $this->factory->create(
            $entityType,
            $this->user,
            $type
        );

        $this->assertEquals($applier, $resultApplier);
    }
}
